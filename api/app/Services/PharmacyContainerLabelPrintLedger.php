<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Retained simulated output evidence; never dispatches to a printer. */
class PharmacyContainerLabelPrintLedger
{
 public function checked(object $print,int $batchId):void
 {
  $payload=array_intersect_key((array)$print,array_flip(['label_id','created_by','document_hash','copies','occurred_on','reason','reference']));
  foreach(['label_id','created_by','copies'] as $key){$payload[$key]=(int)$payload[$key];}
  abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id',$batchId)->where('actor_id',$print->created_by)
   ->where('action','container_label_print_evidence_retained')->where('details->label_id',$print->label_id)->where('details->print_id',$print->id)
   ->where('details->document_hash',$print->document_hash)->where('details->evidence_hash',app(PharmacyCompoundingIncident::class)->digest($payload))->exists(),409,'Print audit binding is missing or changed; retain new output evidence.');
 }
 public function retain(User $actor, int $labelId, array $input): int
 {
  abort_unless(app()->environment(['local','testing']),503);
  abort_unless($actor->role==='pharmacist' && $actor->status==='active' && $actor->organization_id,403);
  $d=validator($input,['request_id'=>'required|uuid','document_hash'=>'required|string|regex:/^[a-f0-9]{64}$/',
   'copies'=>'required|integer|min:1|max:20','occurred_on'=>'required|date_format:Y-m-d|before_or_equal:today',
   'reason'=>'required|string|max:2000','reference'=>'required|string|max:5000','confirmed'=>'required|accepted'])->validate();
  foreach(['reason','reference'] as $key){abort_unless(trim($d[$key])!=='',422,'Print evidence must not be blank.');}
  return DB::transaction(function() use($actor,$labelId,$d){
   DB::table('organizations')->where('id',$actor->organization_id)->lockForUpdate()->first();
   $p=DB::table('pharmacy_container_label_proofs')->where('id',$labelId)->lockForUpdate()->first();abort_unless($p,404);
   $e=DB::table('pharmacy_batch_executions')->find($p->execution_id);
   $b=$e?DB::table('pharmacy_batch_worksheets')->where('id',$e->batch_id)->where('organization_id',$actor->organization_id)->first():null;
   abort_unless($b,404);app(PharmacyAccess::class)->requireLocation($actor,$b->location_id);
   $hash=app(PharmacyCompoundingIncident::class)->digest($d);
   $old=DB::table('pharmacy_container_label_prints')->where('label_id',$labelId)->where('request_id',$d['request_id'])->first();
   if($old){abort_unless((int)$old->created_by===(int)$actor->id && hash_equals($old->request_hash,$hash),409,'Request belongs to different print evidence.');return (int)$old->id;}
   app(PharmacyFinishedContainerLabelLedger::class)->current($actor,$p);
   abort_unless(hash_equals($p->document_hash,$d['document_hash']),409,'Compare the exact retained document before recording output evidence.');
   abort_unless($d['occurred_on']>=substr($p->created_at,0,10),422,'Output evidence cannot predate its retained proof.');
   $print=['label_id'=>$labelId,'created_by'=>$actor->id,
    'request_id'=>$d['request_id'],'request_hash'=>$hash,'document_hash'=>$p->document_hash,'copies'=>$d['copies'],
    'occurred_on'=>$d['occurred_on'],'reason'=>$d['reason'],'reference'=>$d['reference'],'created_at'=>now()];
   $id=DB::table('pharmacy_container_label_prints')->insertGetId($print);
   $payload=array_intersect_key($print,array_flip(['label_id','created_by','document_hash','copies','occurred_on','reason','reference']));
   foreach(['label_id','created_by','copies'] as $key){$payload[$key]=(int)$payload[$key];}
   $evidenceHash=app(PharmacyCompoundingIncident::class)->digest($payload);
   DB::table('pharmacy_compounding_events')->insert(['formulation_id'=>$b->formulation_id,'batch_id'=>$b->id,'actor_id'=>$actor->id,
    'action'=>'container_label_print_evidence_retained','details'=>json_encode(['label_id'=>$labelId,'print_id'=>$id,
     'document_hash'=>$p->document_hash,'copies'=>$d['copies'],'evidence_hash'=>$evidenceHash,'simulated_only'=>true,'printer_command_sent'=>false,'release_enabled'=>false],JSON_THROW_ON_ERROR),'created_at'=>now()]);
   return $id;
  });
 }
}
