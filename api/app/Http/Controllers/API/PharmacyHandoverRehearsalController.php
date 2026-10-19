<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PharmacyAccess;
use App\Services\PharmacyCompoundingIncident;
use App\Services\PharmacyCurrentContainerLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Isolated rehearsal ledger: no stock, real reservation, fill, payment or release writes. */
class PharmacyHandoverRehearsalController extends Controller
{
 private function scope(Request $r,int $executionId):object {
  abort_unless(app()->environment(['local','testing']),503);
  $a=$r->user();abort_unless($a->status==='active' && $a->organization_id && in_array($a->role,['pharmacist','pharmacy_technician'],true),403);
  $e=DB::table('pharmacy_batch_executions')->find($executionId);
  $b=$e?DB::table('pharmacy_batch_worksheets')->where('id',$e->batch_id)->where('organization_id',$a->organization_id)->first():null;
  abort_unless($b,404);app(PharmacyAccess::class)->requireLocation($a,$b->location_id);return $b;
 }
 private function hash(array $v):string{return app(PharmacyCompoundingIncident::class)->digest($v);}
 private function source(Request $r,int $executionId):array {
  $v=app(PharmacyCurrentContainerLabels::class)->inspect($r->user(),$executionId);
  abort_unless($v['all_held_output_labeled'],409,'Complete packaging and current label evidence for every held unit before rehearsing handover.');
  return $v;
 }
 private function audit(Request $r,object $b,string $action,array $details):void {
  DB::table('pharmacy_compounding_events')->insert(['formulation_id'=>$b->formulation_id,'batch_id'=>$b->id,'actor_id'=>$r->user()->id,
   'action'=>$action,'details'=>json_encode($details+['synthetic_only'=>true,'release_enabled'=>false,'stock_changed'=>false],JSON_THROW_ON_ERROR),'created_at'=>now()]);
 }
 public function context(Request $r,int $executionId){$this->scope($r,$executionId);$s=$this->source($r,$executionId);return response()->json(['data'=>['snapshot'=>$s,'source_hash'=>$this->hash($s)],'synthetic_only'=>true,'release_enabled'=>false]);}
 public function index(Request $r,int $executionId){
  $this->scope($r,$executionId);$r->validate(['page'=>'sometimes|integer|min:1']);
  $rows=DB::table('pharmacy_handover_rehearsals')->where('execution_id',$executionId)->orderByDesc('id')->paginate(20,['id','created_by','snapshot','reservation_evidence','status','version','finished_by','finish_record','finished_at','created_at']);
  return response()->json(['data'=>$rows,'can_start'=>! DB::table('pharmacy_handover_rehearsals')->where('execution_id',$executionId)->whereIn('status',['reserved','handed_over'])->exists(),'synthetic_only'=>true,'release_enabled'=>false]);
 }
 public function store(Request $r,int $executionId){
  $b=$this->scope($r,$executionId);abort_unless($r->user()->role==='pharmacist',403);
  $d=$r->validate(['request_id'=>'required|uuid','source_hash'=>'required|string|regex:/^[a-f0-9]{64}$/','evidence'=>'required|string|max:2000','synthetic_confirmed'=>'required|accepted']);
  $hash=$this->hash($d);$old=DB::table('pharmacy_handover_rehearsals')->where('execution_id',$executionId)->where('request_id',$d['request_id'])->first();
  if($old){abort_unless((int)$old->created_by===(int)$r->user()->id && hash_equals($old->request_hash,$hash),409,'Request belongs to another rehearsal.');return response()->json(['data'=>['id'=>$old->id],'synthetic_only'=>true,'release_enabled'=>false]);}
  abort_if(DB::table('pharmacy_handover_rehearsals')->where('execution_id',$executionId)->whereIn('status',['reserved','handed_over'])->exists(),409,'Resolve the existing simulated reservation. Completed rehearsal handovers cannot be reserved twice.');
  $source=$this->source($r,$executionId);abort_unless(hash_equals($d['source_hash'],$this->hash($source)),409,'Container or label evidence changed. Refresh and start again.');
  $id=DB::table('pharmacy_handover_rehearsals')->insertGetId(['execution_id'=>$executionId,'created_by'=>$r->user()->id,'request_id'=>$d['request_id'],'request_hash'=>$hash,
   'snapshot'=>json_encode($source,JSON_THROW_ON_ERROR),'snapshot_hash'=>$this->hash($source),'reservation_evidence'=>$d['evidence'],'created_at'=>now()]);
  $this->audit($r,$b,'handover_rehearsal_reserved',['rehearsal_id'=>$id,'snapshot_hash'=>$this->hash($source),'evidence_hash'=>$this->hash(['evidence'=>$d['evidence']])]);
  return response()->json(['data'=>['id'=>$id],'synthetic_only'=>true,'release_enabled'=>false],201);
 }
 public function finish(Request $r,int $executionId,int $rehearsalId){
  $b=$this->scope($r,$executionId);abort_unless($r->user()->role==='pharmacist',403);
  $row=DB::table('pharmacy_handover_rehearsals')->where('execution_id',$executionId)->where('id',$rehearsalId)->first();abort_unless($row,404);
  $d=$r->validate(['request_id'=>'required|uuid','version'=>'required|integer|min:1','action'=>'required|in:cancel,hand_over','evidence'=>'required|string|max:2000','synthetic_confirmed'=>'required|accepted',
   'recipient_reference'=>'required_if:action,hand_over|prohibited_if:action,cancel|string|max:2000',
   'identity_reference'=>'required_if:action,hand_over|prohibited_if:action,cancel|string|max:2000',
   'counseling_reference'=>'required_if:action,hand_over|prohibited_if:action,cancel|string|max:2000',
   'comparisons'=>'required_if:action,hand_over|prohibited_if:action,cancel|array|min:1|max:100',
   'comparisons.*'=>'required|array:identifier,label_code', 'comparisons.*.identifier'=>'required|string|max:64|distinct', 'comparisons.*.label_code'=>'required|string|max:32|distinct']);
  $hash=$this->hash($d);
  if($row->status!=='reserved'){abort_unless((int)$row->finished_by===(int)$r->user()->id && $row->finish_request_id===$d['request_id'] && hash_equals($row->finish_hash,$hash),409,'This rehearsal already has a final outcome.');return $this->index($r,$executionId);}
  abort_unless((int)$row->version===(int)$d['version'],409,'Rehearsal version changed.');
  $source=json_decode($row->snapshot,true,512,JSON_THROW_ON_ERROR);
  abort_unless(hash_equals($row->snapshot_hash,$this->hash($source)),409,'Retained rehearsal snapshot changed.');
  abort_unless(DB::table('pharmacy_compounding_events')->where('batch_id',$b->id)->where('action','handover_rehearsal_reserved')->where('actor_id',$row->created_by)
   ->where('details->rehearsal_id',$row->id)->where('details->snapshot_hash',$row->snapshot_hash)->where('details->evidence_hash',$this->hash(['evidence'=>$row->reservation_evidence]))->exists(),409,'Retained reservation audit is missing or changed.');
  if($d['action']==='hand_over'){
   abort_if((int)$row->created_by===(int)$r->user()->id,422,'A different assigned pharmacist must complete the synthetic handover check.');
   $author=User::find($row->created_by);abort_unless($author && $author->status==='active' && $author->role==='pharmacist' && (int)$author->organization_id===(int)$r->user()->organization_id,409,'Reservation author is no longer authorized.');
   app(PharmacyAccess::class)->requireLocation($author,$b->location_id);
   abort_unless(hash_equals($row->snapshot_hash,$this->hash($this->source($r,$executionId))),409,'Source changed since the simulated reservation. Cancel this rehearsal and reconcile the current evidence.');
   $expected=collect($source['containers'])->mapWithKeys(fn($c)=>[$c['container']['identifier']=>$c['barcode_code']])->all();
   $observed=collect($d['comparisons'])->mapWithKeys(fn($c)=>[$c['identifier']=>$c['label_code']])->all();ksort($expected);ksort($observed);
   abort_unless($expected===$observed,422,'Compare every exact container and current label code; partial or mismatched packages cannot pass.');
  }
  $record=$d;unset($record['request_id'],$record['version']);
  DB::table('pharmacy_handover_rehearsals')->where('id',$row->id)->update(['status'=>$d['action']==='hand_over'?'handed_over':'cancelled','version'=>$row->version+1,
   'finished_by'=>$r->user()->id,'finish_request_id'=>$d['request_id'],'finish_hash'=>$hash,'finish_record'=>json_encode($record,JSON_THROW_ON_ERROR),'finished_at'=>now()]);
  $this->audit($r,$b,'handover_rehearsal_finished',['rehearsal_id'=>$row->id,'outcome'=>$d['action'],'evidence_hash'=>$this->hash($record)]);
  return $this->index($r,$executionId);
 }
}
