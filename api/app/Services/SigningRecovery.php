<?php
namespace App\Services;

use App\Models\{Document, Signature, User};
use Illuminate\Support\Facades\{DB, Http, Storage};
use App\Exceptions\SigningRecoveryException;

class SigningRecovery
{
    public static function recipients(Document $document): array
    {
        return $document->signers()->orderBy('signing_order')->orderBy('id')->get()->values()->map(fn ($signer,$index) => [
            'recipientId'=>(string)($index+1), 'routingOrder'=>(int)$signer->signing_order,
            'email'=>strtolower(trim($signer->email)), 'name'=>trim($signer->name),
        ])->all();
    }
    public static function fingerprint(array $recipients): string
    {
        return hash('sha256',json_encode($recipients,JSON_THROW_ON_ERROR));
    }
    public static function snapshot(Document $document, string $account, string $base, string $bytes): array
    {
        return ['version'=>1,'organization_id'=>$document->organization_id,'account_id'=>$account,'base_uri'=>$base,
            'claimed_at'=>now()->toIso8601String(),'source_sha256'=>hash('sha256',$bytes),
            'name_sha256'=>hash('sha256',$document->original_name),'recipients_sha256'=>self::fingerprint(self::recipients($document))];
    }
    private function unchanged(Document $document): void
    {
        $snapshot=$document->docusign_dispatch_snapshot;
        if (!is_array($snapshot) || ($snapshot['version'] ?? null)!==1 || ($snapshot['organization_id'] ?? null)!==$document->organization_id
            || ($snapshot['name_sha256'] ?? '')!==hash('sha256',$document->original_name)
            || ($snapshot['source_sha256'] ?? '')!==hash('sha256',Storage::disk($document->disk())->get($document->path))
            || ($snapshot['recipients_sha256'] ?? '')!==self::fingerprint(self::recipients($document))) {
            throw new SigningRecoveryException('Dispatch evidence is missing or changed. Manual investigation is required; the send guard remains.');
        }
    }
    public function inspect(Document $document, DocuSignService $provider): array
    {
        if (!$document->docusign_dispatch_id || $document->docusign_envelope_id || $document->signature_status!=='not_sent') throw new SigningRecoveryException('This document is not an unresolved dispatch.');
        $this->unchanged($document);
        $token=$provider->getAccessToken($document->organization_id);
        $account=(string)$provider->getAccountId(); $base=DocuSignService::validateBaseUri($provider->getBaseUri());
        $snapshot=$document->docusign_dispatch_snapshot;
        if ($account!==($snapshot['account_id'] ?? null) || $base!==($snapshot['base_uri'] ?? null)) throw new SigningRecoveryException('Signing account or environment changed. The send guard remains.');
        $endpoint=$base.'/restapi/v2.1/accounts/'.rawurlencode($account).'/envelopes';
        $lookup=$this->get($endpoint,$token,['transaction_ids'=>$document->docusign_dispatch_id]);
        $matches=$lookup['envelopes'] ?? null;
        if (!is_array($matches) || count($matches)!==1) throw new SigningRecoveryException('No unique envelope was found. This does not prove non-delivery; the send guard remains.');
        $id=$matches[0]['envelopeId'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9-]{1,128}$/D',$id)) throw new SigningRecoveryException('Invalid envelope response.');
        $summary=$this->get($endpoint.'/'.rawurlencode($id),$token);
        $status=$summary['status'] ?? null;
        if (($summary['envelopeId'] ?? null)!==$id || !in_array($status,['sent','delivered','completed','declined','voided'],true)
            || (isset($summary['transactionId']) && $summary['transactionId']!==$document->docusign_dispatch_id)
            || (isset($matches[0]['transactionId']) && $matches[0]['transactionId']!==$document->docusign_dispatch_id)) throw new SigningRecoveryException('Envelope identity or state is unconfirmed.');
        $recipients=$this->get($endpoint.'/'.rawurlencode($id).'/recipients',$token);
        $signers=$recipients['signers'] ?? null;
        if (!is_array($signers) || !$signers) throw new SigningRecoveryException('Recipient evidence is missing.');
        foreach ($recipients as $key=>$value) if ($key!=='signers' && is_array($value) && count($value)) throw new SigningRecoveryException('Unexpected recipient types require manual review.');
        $identities=[]; $states=[];
        foreach ($signers as $signer) {
            if (!is_array($signer) || !is_string($signer['email'] ?? null) || !is_string($signer['name'] ?? null)
                || !isset($signer['recipientId'],$signer['routingOrder']) || !in_array($signer['status'] ?? null,['created','sent','delivered','completed','declined'],true)) throw new SigningRecoveryException('Recipient evidence is invalid.');
            $identities[]=['recipientId'=>(string)$signer['recipientId'],'routingOrder'=>(int)$signer['routingOrder'],'email'=>strtolower(trim($signer['email'])),'name'=>trim($signer['name'])];
            $states[(string)$signer['recipientId']]=$signer['status'];
        }
        usort($identities,fn($a,$b)=>(int)$a['recipientId']<=>(int)$b['recipientId']);
        if (self::fingerprint($identities)!==$snapshot['recipients_sha256']) throw new SigningRecoveryException('Recipients do not match the saved dispatch.');
        if ($status==='completed' && count(array_filter($states,fn($state)=>$state!=='completed'))) throw new SigningRecoveryException('Envelope and recipient completion states disagree.');
        $files=$this->get($endpoint.'/'.rawurlencode($id).'/documents',$token);
        $documents=array_values(array_filter($files['envelopeDocuments'] ?? [],fn($file)=>($file['documentId'] ?? null)!=='certificate'));
        if (count($documents)!==1 || (string)($documents[0]['documentId'] ?? '')!=='1' || ($documents[0]['name'] ?? null)!==$document->original_name) throw new SigningRecoveryException('Document identity does not match the saved dispatch.');
        return ['envelope_id'=>$id,'status'=>$status,'recipient_states'=>$states,'dispatch_id'=>$document->docusign_dispatch_id,'snapshot'=>$snapshot];
    }
    private function get(string $url,string $token,array $query=[]): array
    {
        $response=Http::withToken($token)->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->get($url,$query);
        if (!$response->successful() || !is_array($response->json())) throw new SigningRecoveryException('Provider lookup failed. The send guard remains.');
        return $response->json();
    }
    public function apply(Document $document, array $candidate, User $actor, string $expectedEnvelope, string $reason): void
    {
        if ($expectedEnvelope!==$candidate['envelope_id'] || trim($reason)==='' || strlen($reason)>300) throw new SigningRecoveryException('Exact envelope ID and a short audit reason are required.');
        DB::transaction(function () use ($document,$candidate,$actor,$reason) {
            $locked=Document::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $operator=User::withoutGlobalScopes()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($operator->status!=='active' || !in_array($operator->role,['admin','firm_admin'],true) || ($operator->role!=='admin' && $operator->organization_id!==$locked->organization_id)) throw new SigningRecoveryException('An active authorized administrator is required.');
            if ($locked->docusign_envelope_id || $locked->signature_status!=='not_sent' || $locked->docusign_dispatch_id!==$candidate['dispatch_id'] || $locked->docusign_dispatch_snapshot!==$candidate['snapshot']) throw new SigningRecoveryException('Dispatch state changed; inspect it again.');
            $this->unchanged($locked);
            $status=$candidate['status'];
            $locked->update(['docusign_envelope_id'=>$candidate['envelope_id'],'signature_status'=>in_array($status,['completed','declined','voided'])?$status:'pending',
                'document_status'=>$status==='completed'?'signed':'sent_for_signature','sent_at'=>$locked->sent_at ?: now(),'signed_at'=>$status==='completed'?now():null]);
            foreach ($locked->signers()->orderBy('signing_order')->orderBy('id')->get()->values() as $index=>$signer) {
                $recipientStatus=$candidate['recipient_states'][(string)($index+1)];
                $signer->update(['docusign_recipient_id'=>(string)($index+1),'status'=>match($recipientStatus){'completed'=>'signed','declined'=>'declined','created'=>'pending',default=>'sent'},'signed_at'=>$recipientStatus==='completed'?now():null]);
            }
            Signature::create(['organization_id'=>$locked->organization_id,'document_id'=>$locked->id,'provider'=>'docusign','provider_envelope_id'=>$candidate['envelope_id'],
                'provider_event'=>'envelope_reconciled','status'=>match($status){'completed'=>'completed','declined'=>'declined','voided'=>'failed',default=>'pending'},
                'provider_payload'=>['actor_id'=>$operator->id,'reason'=>$reason,'dispatch_id'=>$candidate['dispatch_id']],'processed_at'=>now()]);
        });
    }
}
