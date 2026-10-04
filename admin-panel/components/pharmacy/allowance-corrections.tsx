'use client'
import {useState} from 'react'
import {PharmacyRx,usePharmacyActionMutation} from '@/store/api/pharmacyApiSlice'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'

export function AllowanceCorrections({rx,role,userId}:{rx:PharmacyRx;role:string;userId:number}){
 const b=rx.quantity_balance
 const [save,{isLoading}]=usePharmacyActionMutation()
 const [error,setError]=useState('')
 const [requestId]=useState(()=>crypto.randomUUID())
 if(!b.closure_history.length)return null
 async function submit(path:string,body:Record<string,unknown>){setError('');try{await save({id:rx.id,path,body:{...body,request_id:requestId,ledger_token:b.ledger_token,confirmed:true}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <Card><CardHeader><CardTitle>Allowance decisions and corrections</CardTitle></CardHeader><CardContent className="space-y-4">
 <p className="text-sm">Original closure decisions remain below. A correction records an error in that decision; it is not a new prescription, refill or clinical authorization. A different assigned pharmacist must review it. Later supplies, open fills, expiry or discontinuation can prevent reopening.</p>
 {b.pending_correction_id&&<p role="status" className="border rounded p-3">New fills and allowance closures are held pending independent review. Existing stock and completed supplies are unchanged.</p>}
 {b.closure_history.map(c=><section key={c.id} className="border rounded p-3 space-y-3 break-words">
 <h3 className="font-semibold">{c.authorization_number===1?'Original allowance':`Refill ${c.authorization_number-1}`} · closure #{c.id} · {c.corrected?'Corrected; original decision retained':'Closed without supply'}</h3>
 <p>{c.quantity} {rx.quantity_unit} · {c.basis.replaceAll('_',' ')} · Decision {c.occurred_on}</p><p>{c.reason}</p><p>Evidence: {c.evidence}</p><p className="text-sm">Pharmacist #{c.actor_id} · Recorded {c.created_at}</p>
 {c.corrections.map(r=><div key={r.id} className="border-t pt-3 space-y-2">
 <h4 className="font-semibold">Correction #{r.id} · {r.status}</h4><p>{r.reason}</p><p>Evidence: {r.evidence}</p><p className="text-sm">Requested by pharmacist #{r.created_by} · {r.created_at}</p>
 {r.reviewed_at&&<><p>Review: {r.review_evidence}</p><p className="text-sm">Pharmacist #{r.reviewed_by} · {r.reviewed_at}</p></>}
 {r.status==='pending'&&role==='pharmacist'&&(r.created_by===userId?<p>A different assigned pharmacist must review this request.</p>:<Review disabled={isLoading} eligible={b.correctable_closure_id===c.id} quantity={`${c.quantity} ${rx.quantity_unit}`} save={body=>submit(`allowance-corrections/${r.id}/review`,body)}/>)}
 </div>)}
 {role==='pharmacist'&&!b.pending_correction_id&&b.correctable_closure_id===c.id&&<details><summary className="cursor-pointer underline">Request correction of this closure</summary><form className="grid gap-3 mt-3" onSubmit={e=>{e.preventDefault();submit(`allowances/${c.id}/corrections`,Object.fromEntries(new FormData(e.currentTarget)))}}>
 <p className="text-sm">Request independent review to reopen exactly {c.quantity} {rx.quantity_unit} in this original allowance. While pending, new fills are held. Nothing is added to stock or moved to another refill.</p>
 <Field name="reason" label="Error in the original closure decision" maxLength={2000}/><Field name="evidence" label="Verified instruction / correction evidence" maxLength={2000}/>
 <label className="flex items-start gap-2 text-sm"><input type="checkbox" required/>I checked the original decision and supporting authority and am requesting independent review of this correction.</label><Button className="h-auto whitespace-normal" disabled={isLoading}>Request correction and hold new fills</Button>
 </form></details>}
 </section>)}
 {error&&<p role="alert">{error}</p>}
 </CardContent></Card>
}
function Review({disabled,eligible,quantity,save}:{disabled:boolean;eligible:boolean;quantity:string;save:(body:Record<string,unknown>)=>void}){
 return <form className="grid gap-3" onSubmit={e=>{e.preventDefault();save(Object.fromEntries(new FormData(e.currentTarget)))}}>
 <p className="text-sm">Applying restores only {quantity} to the same allowance. Rejecting leaves its closure intact. Neither changes stock, handovers or billing. Verify the instruction and all retained history before deciding.</p>
 {!eligible&&<p role="status">This closure is no longer eligible to reopen. Reject the request and resolve the prescription history.</p>}
 <label className="grid gap-1 text-sm">Independent decision<select required name="decision" defaultValue="" className="border rounded p-2 bg-background"><option value="">Select a decision</option><option value="apply" disabled={!eligible}>Apply correction and reopen remainder</option><option value="reject">Reject correction</option></select></label>
 <Field name="evidence" label="Independent review evidence" maxLength={2000}/><label className="flex items-start gap-2 text-sm"><input type="checkbox" required/>I independently verified the decision, original allowance and supporting evidence.</label>
 <Button className="h-auto whitespace-normal" disabled={disabled}>Record independent decision</Button>
 </form>
}
