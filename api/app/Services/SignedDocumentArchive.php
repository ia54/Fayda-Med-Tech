<?php
namespace App\Services;

use App\Models\{Document, Signature};
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SignedDocumentArchive
{
    private const PDF_LIMIT = 25 * 1024 * 1024;
    private const CERTIFICATE_LIMIT = 5 * 1024 * 1024;

    public function retrieve(Document $document, DocuSignService $provider): Signature
    {
        if ($document->signature_status !== 'completed' || !$document->docusign_envelope_id) {
            throw new RuntimeException('A completed envelope is required.');
        }
        if ($cached = $this->cached($document)) return $cached;
        $paths = [];
        try {
            $token = $provider->getAccessToken($document->organization_id);
            $base = DocuSignService::validateBaseUri($provider->getBaseUri());
            $account = $provider->getAccountId();
            if (!$token || !$account) throw new RuntimeException('Incomplete signing account.');
            $endpoint = $base.'/restapi/v2.1/accounts/'.rawurlencode((string) $account).'/envelopes/'.rawurlencode($document->docusign_envelope_id);
            $status = Http::withToken($token)->acceptJson()->connectTimeout(10)->timeout(30)->withoutRedirecting()->get($endpoint);
            if (!$status->successful() || $status->json('envelopeId') !== $document->docusign_envelope_id || $status->json('status') !== 'completed') {
                throw new RuntimeException('Provider completion is unconfirmed.');
            }
            $pdf = $this->download($endpoint.'/documents/combined', $token, self::PDF_LIMIT, ['certificate'=>'false']);
            $certificate = $this->download($endpoint.'/documents/certificate', $token, self::CERTIFICATE_LIMIT);
            $prefix = 'signed/docusign/'.$document->id.'/'.Str::uuid();
            $paths = [$prefix.'/signed.pdf', $prefix.'/certificate.pdf'];
            $disk = Storage::disk('documents');
            if (!$disk->put($paths[0], $pdf, ['visibility'=>'private']) || !$disk->put($paths[1], $certificate, ['visibility'=>'private'])) {
                throw new RuntimeException('Private storage write failed.');
            }
            // Publish both files atomically. Concurrent downloads may fetch twice,
            // but only one archive is kept and no lock is held during provider I/O.
            $archive = DB::transaction(function () use ($document, $paths, $pdf, $certificate) {
                $locked = Document::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($document->id)->lockForUpdate()->firstOrFail();
                if ($locked->docusign_envelope_id !== $document->docusign_envelope_id || $locked->signature_status !== 'completed' || $locked->organization_id !== $document->organization_id) {
                    throw new RuntimeException('Document changed during retrieval.');
                }
                if ($existing = $this->cached($locked)) return $existing;
                return Signature::create([
                    'organization_id'=>$locked->organization_id, 'document_id'=>$locked->id,
                    'provider'=>'docusign', 'provider_envelope_id'=>$locked->docusign_envelope_id,
                    'provider_event'=>'documents_archived', 'status'=>'completed',
                    'signed_file_path'=>$paths[0], 'processed_at'=>now(),
                    'provider_payload'=>['certificate_path'=>$paths[1], 'pdf_sha256'=>hash('sha256',$pdf), 'certificate_sha256'=>hash('sha256',$certificate)],
                ]);
            });
            if ($archive->signed_file_path !== $paths[0]) $disk->delete($paths);
            return $archive;
        } catch (Throwable $exception) {
            if ($paths) Storage::disk('documents')->delete($paths);
            throw new RuntimeException('Signed documents are temporarily unavailable. Please retry or contact your administrator.');
        }
    }

    private function cached(Document $document): ?Signature
    {
        $archive = Signature::withoutGlobalScopes()->where('document_id',$document->id)->where('organization_id',$document->organization_id)
            ->where('provider','docusign')->where('provider_envelope_id',$document->docusign_envelope_id)
            ->where('provider_event','documents_archived')->where('status','completed')->latest('id')->first();
        if (!$archive) return null;
        $metadata = $archive->provider_payload ?? [];
        $prefix = 'signed/docusign/'.$document->id.'/';
        foreach ([[$archive->signed_file_path,$metadata['pdf_sha256'] ?? '',self::PDF_LIMIT],[$metadata['certificate_path'] ?? null,$metadata['certificate_sha256'] ?? '',self::CERTIFICATE_LIMIT]] as [$path,$hash,$limit]) {
            $disk = Storage::disk('documents');
            if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash) || !is_string($path) || !str_starts_with($path,$prefix) || str_contains($path,'..') || !$disk->exists($path) || $disk->size($path)>$limit || !hash_equals($hash,hash('sha256',$disk->get($path)))) return null;
        }
        return $archive;
    }

    private function download(string $url, string $token, int $limit, array $query = []): string
    {
        $response = Http::withToken($token)->accept('application/pdf')->connectTimeout(10)->timeout(60)->withoutRedirecting()
            ->withOptions(['stream'=>true,'read_timeout'=>30])->get($url,$query);
        $stream = $response->toPsrResponse()->getBody();
        try {
            if (!$response->successful() || strtolower(trim(explode(';',$response->header('Content-Type'))[0])) !== 'application/pdf') throw new RuntimeException('Invalid PDF response.');
            if ((int)$response->header('Content-Length') > $limit) throw new RuntimeException('PDF exceeds archive limit.');
            $content = '';
            while (!$stream->eof()) {
                $chunk = $stream->read(min(65536,$limit + 1 - strlen($content)));
                if ($chunk === '' && !$stream->eof()) throw new RuntimeException('Incomplete PDF response.');
                $content .= $chunk;
                if (strlen($content)>$limit) throw new RuntimeException('PDF exceeds archive limit.');
            }
            if (!str_starts_with($content,'%PDF-') || !str_contains(substr($content,-1024),'%%EOF')) throw new RuntimeException('Incomplete PDF document.');
            return $content;
        } finally { $stream->close(); }
    }
}
