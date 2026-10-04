<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Documentary code comparison; physical scanner and dispensing acceptance remain separate. */
class PharmacyContainerLabelComparisonLedger
{
 public function retain(User $actor,int $labelId,array $input):int
 {
  abort_unless(app()->environment(['local','testing']),503);
  abort_unless($actor->role==='pharmacist' && $actor->status==='active' && $actor->organization_id,403);
  $d=validator($input,['request_id'=>'required|uuid','print_id'=>'required|integer|min:1','label_code'=>'required|string|max:32',
   'container_identifier'=>'required|string|max:64','document_hash'=>'required|string|regex:/^[a-f0-9]{64}$/',
   'input_method'=>'required|in:scanner,manual','manual_reason'=>'nullable|required_if:input_method,manual|string|max:2000',
   'evidence'=>'required|string|max:5000','confirmed'=>'required|accepted'])->validate();
  abort_unless(trim($d['evidence'])!=='',422,'Comparison evidence is required.');
  if($d['input_method']==='manual'){abort_unless(trim((string)($d['manual_reason']??''))!=='',422,'A manual comparison reason is required.');}
  else{abort_if(!empty($d['manual_reason']),422,'Use manual input when retaining a manual comparison reason.');}
  return DB::transaction(function() use($actor,$labelId,$d){
   DB::table('organizations')->where('id',$actor->organization_id)->lockForUpdate()->first();
   $p=DB::table('pharmacy_container_label_proofs')->where('id',$labelId)->lockForUpdate()->first();abort_unless($p,404);
   $e=DB::table('pharmacy_batch_executions')->find($p->execution_id);
   $b=$e?DB::table('pharmacy_batch_worksheets')->where('id',$e->batch_id)->where('organization_id',$actor->organization_id)->first():null;
   abort_unless($b,404);app(PharmacyAccess::class)->requireLocation($actor,$b->location_id);
   $hash=app(PharmacyCompoundingIncident::class)->digest($d);
   $old=DB::table('pharmacy_container_label_comparisons')->where('label_id',$labelId)->where('request_id',$d['request_id'])->first();
   if($old){abort_unless((int)$old->created_by===(int)$actor->id && hash_equals($old->request_hash,$hash),409,'Request belongs to different comparison evidence.');return (int)$old->id;}
   app(PharmacyFinishedContainerLabelLedger::class)->current($actor,$p);
   abort_unless($p->barcode_code && hash_equals($p->barcode_code,$d['label_code']) && hash_equals($p->container_identifier,$d['container_identifier']),422,'Label code or container identity does not match. Stop and reconcile the proof.');
   abort_unless(hash_equals($p->document_hash,$d['document_hash']),409,'The compared document differs from the retained proof.');
   $print=DB::table('pharmacy_container_label_prints')->where('id',$d['print_id'])->where('label_id',$labelId)->first();
   abort_unless($print && hash_equals($print->document_hash,$p->document_hash),422,'Select print evidence for this exact proof.');
   app(PharmacyContainerLabelPrintLedger::class)->checked($print,(int)$b->id);
   $comparison = ['label_id'=>$labelId,'print_id'=>$print->id,'created_by'=>$actor->id,
    'request_id'=>$d['request_id'],'request_hash'=>$hash,'document_hash'=>$p->document_hash,'barcode_code'=>$p->barcode_code,
    'container_identifier'=>$p->container_identifier,'input_method'=>$d['input_method'],'manual_reason'=>$d['manual_reason']??null,'evidence'=>$d['evidence'],'created_at'=>now()];
   $id=DB::table('pharmacy_container_label_comparisons')->insertGetId($comparison);
   $evidenceHash=app(PharmacyCompoundingIncident::class)->digest(array_intersect_key($comparison,array_flip(['label_id','print_id','created_by','document_hash','barcode_code','container_identifier','input_method','manual_reason','evidence'])));
   DB::table('pharmacy_compounding_events')->insert(['formulation_id'=>$b->formulation_id,'batch_id'=>$b->id,'actor_id'=>$actor->id,
    'action'=>'container_label_comparison_retained','details'=>json_encode(['label_id'=>$labelId,'comparison_id'=>$id,'print_id'=>$print->id,
     'document_hash'=>$p->document_hash,'evidence_hash'=>$evidenceHash,'physical_device_verified'=>false,'release_enabled'=>false],JSON_THROW_ON_ERROR),'created_at'=>now()]);
   return $id;
  });
 }
}
