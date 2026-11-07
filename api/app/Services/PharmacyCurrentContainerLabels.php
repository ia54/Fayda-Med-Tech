<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Revalidates documentary label lineage for all nonempty containers. Never grants release. */
class PharmacyCurrentContainerLabels
{
 public function inspect(User $actor,int $executionId):array
 {
  abort_unless(app()->environment(['local','testing']),503);
  abort_unless($actor->role==='pharmacist' && $actor->status==='active' && $actor->organization_id,403);
  return DB::transaction(function() use($actor,$executionId){
   DB::table('organizations')->where('id',$actor->organization_id)->lockForUpdate()->first();
   $review=app(PharmacyReviewedSuitabilityContext::class)->inspect($actor,$executionId);
   $context=$review['current_container_evidence'];$batch=$context['custody']['batch'];
   $containers=$context['containers_for_review'];abort_unless(count($containers)>0,409,'No nonempty containers have current label prerequisites.');
   $digest=app(PharmacyCompoundingIncident::class);$results=[];
   foreach($containers as $container){
    $proof=DB::table('pharmacy_container_label_proofs')->where('execution_id',$executionId)->where('container_identifier',$container['identifier'])->orderByDesc('revision')->first();
    abort_unless($proof && $proof->barcode_code,409,'Every nonempty container requires a current barcode-bearing proof.');
    app(PharmacyFinishedContainerLabelLedger::class)->current($actor,$proof);
    $comparison=DB::table('pharmacy_container_label_comparisons')->where('label_id',$proof->id)->orderByDesc('id')->first();
    abort_unless($comparison,409,'Every current proof requires retained code-comparison evidence.');
    abort_unless(hash_equals($proof->document_hash,$comparison->document_hash) && hash_equals($proof->barcode_code,$comparison->barcode_code)
     && hash_equals($proof->container_identifier,$comparison->container_identifier),409,'Compared label identity or document differs.');
    abort_unless(in_array($comparison->input_method,['manual','scanner'],true) && trim($comparison->evidence)!==''
     && ($comparison->input_method==='manual'?trim((string)$comparison->manual_reason)!=='':empty($comparison->manual_reason)),409,'Comparison method evidence is incomplete.');
    $print=DB::table('pharmacy_container_label_prints')->where('id',$comparison->print_id)->where('label_id',$proof->id)->first();
    abort_unless($print && hash_equals($print->document_hash,$proof->document_hash),409,'Compared print evidence is missing or differs.');
    foreach([$proof->created_by,$print->created_by,$comparison->created_by] as $staffId){
     $staff=User::find($staffId);abort_unless($staff && $staff->role==='pharmacist' && $staff->status==='active' && (int)$staff->organization_id===(int)$actor->organization_id,409,'Label evidence contributor is no longer authorized.');
     app(PharmacyAccess::class)->requireLocation($staff,$batch['location_id']);
    }
    app(PharmacyContainerLabelPrintLedger::class)->checked($print,(int)$batch['id']);
    $payload=array_intersect_key((array)$comparison,array_flip(['label_id','print_id','created_by','document_hash','barcode_code','container_identifier','input_method','manual_reason','evidence']));
    foreach(['label_id','print_id','created_by'] as $key){$payload[$key]=(int)$payload[$key];}
    abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id',$batch['id'])->where('actor_id',$comparison->created_by)
     ->where('action','container_label_comparison_retained')->where('details->label_id',$proof->id)->where('details->comparison_id',$comparison->id)
     ->where('details->print_id',$print->id)->where('details->document_hash',$proof->document_hash)->where('details->evidence_hash',$digest->digest($payload))->exists(),409,'Current comparison audit binding is missing or changed; retain a new comparison.');
    $results[]=['container'=>$container,'proof_id'=>(int)$proof->id,'proof_revision'=>(int)$proof->revision,'document_hash'=>$proof->document_hash,
     'barcode_code'=>$proof->barcode_code,'print_id'=>(int)$print->id,'comparison_id'=>(int)$comparison->id,'input_method'=>$comparison->input_method];
   }
   $unpackaged=$context['custody']['container_balance']['unpackaged_quantity'];
   return ['execution_id'=>$executionId,'suitability_id'=>(int)$review['suitability_proposal']['id'],'containers'=>$results,
    'unit'=>$context['custody']['container_balance']['unit'],'unpackaged_quantity'=>$unpackaged,'all_held_output_labeled'=>PharmacyStock::milli($unpackaged)===0,
    'physical_device_verified'=>false,'operational_acceptance_verified'=>false,'release_enabled'=>false];
  });
 }
}
