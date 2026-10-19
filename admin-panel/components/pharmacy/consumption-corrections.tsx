"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {CompoundBatch,ConsumptionCorrectionProposal,useGetConsumptionCorrectionsQuery,useGetConsumptionContextQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Choice,Field,errorMessage} from './fields'

export function ConsumptionCorrections({batch}:{batch:CompoundBatch}){
 const {user}=useAuth();const execution=batch.execution
 const [page,setPage]=useState(1);const [ingredient,setIngredient]=useState('')
 const {currentData,isFetching,isError,refetch}=useGetConsumptionCorrectionsQuery({id:execution?.id||0,page},{skip:!execution})
 if(!execution)return null
 const pharmacist=user?.role==='pharmacist'
 const independent=pharmacist&&Number(user?.id)!==execution.created_by&&!execution.addenda.some(a=>a.created_by===Number(user?.id))
 return <section className="border-t pt-4 space-y-3">
  <h4 className="font-semibold">Ingredient consumption corrections</h4>
  <p>Preserve the original preparation record while correcting measured ingredient consumption. A pending proposal holds the receipt and linked batches. Independent application adjusts stock once and keeps the receipt and affected output quarantined.</p>
  {isError?<p role="alert">Could not load ingredient corrections. <Button onClick={()=>refetch()}>Retry ingredient corrections</Button></p>:!currentData?<p>Loading ingredient corrections…</p>:<>
   {!currentData.data.total&&<p>No ingredient corrections recorded.</p>}
   {currentData.data.data.map(p=><article key={p.id} className="border rounded p-3 space-y-2">
    <h5 className="font-medium">Ingredient {p.ingredient_key} · Receipt {p.ingredient_lot_id} · Correction {p.id} · {p.status}</h5>
    <p>Original consumption: {p.proposal.original_consumed}. Previously accounted: {p.proposal.previous_accounted_consumed}. Proposed consumption: {p.proposal.corrected_consumed} {p.proposal.unit}.</p>
    <p>Receipt balance: {p.proposal.previous_on_hand} → {p.proposal.corrected_on_hand} {p.proposal.unit}. Reserved quantity stays {p.proposal.reserved_unchanged}. Stock {p.proposal.stock_direction}: {p.proposal.stock_change_quantity}.</p>
    <p className="whitespace-pre-wrap break-words">Reason: {p.correction_evidence.reason}</p>
    <p className="whitespace-pre-wrap break-words">Measurement: {p.correction_evidence.measurement_evidence}</p>
    <p className="whitespace-pre-wrap break-words">Original source: {p.correction_evidence.source_evidence}</p>
    <p className="whitespace-pre-wrap break-words">Receipt count: {p.correction_evidence.receipt_count_evidence}</p>
    {p.review_evidence&&<p className="whitespace-pre-wrap break-words">Review: {p.review_evidence}</p>}
    {p.status==='pending'&&(independent&&Number(user?.id)!==p.created_by?<ConsumptionDecision proposal={p} disabled={isFetching}/>:<p>A pharmacist who did not author the proposal, execution or addenda must review.</p>)}
   </article>)}
   <div className="flex gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous ingredient corrections</Button><span>Page {page} of {currentData.data.last_page}</span><Button variant="outline" disabled={page>=currentData.data.last_page||isFetching} onClick={()=>setPage(page+1)}>Next ingredient corrections</Button></div>
   {pharmacist&&<><label className="grid gap-1">Ingredient to correct<select value={ingredient} onChange={e=>setIngredient(e.target.value)} className="border rounded p-2 bg-background"><option value="">Select a recorded ingredient</option>{execution.record.ingredients.map(i=><option key={i.key} value={i.key}>{batch.formula.record.ingredients.find(f=>f.key===i.key)?.name||i.key} · {i.key}</option>)}</select></label>{ingredient&&<ConsumptionProposal key={`${execution.id}:${ingredient}`} executionId={execution.id} ingredientKey={ingredient} onSaved={()=>setPage(1)}/>}</>}
  </>}
 </section>
}
function ConsumptionProposal({executionId,ingredientKey,onSaved}:{executionId:number;ingredientKey:string;onSaved:()=>void}){
 const {currentData,isFetching,isError,refetch}=useGetConsumptionContextQuery({id:executionId,key:ingredientKey})
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const request=useRef<{body:string;id:string}|null>(null)
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');if(!currentData)return
  const form=e.currentTarget;const c=currentData.data;const body={...Object.fromEntries(new FormData(form)),unit:c.unit,version:c.execution_version,receipt_version:c.receipt_version}
  const serialized=JSON.stringify(body);if(request.current?.body!==serialized)request.current={body:serialized,id:crypto.randomUUID()}
  try{await save({path:`executions/${executionId}/ingredients/${encodeURIComponent(ingredientKey)}/consumption-corrections`,body:{...body,request_id:request.current.id}}).unwrap();form.reset();request.current=null;onSaved()}catch(e){setError(errorMessage(e))}
 }
 if(isError)return <p role="alert">Could not load receipt evidence. <Button onClick={()=>refetch()}>Retry receipt evidence</Button></p>
 if(!currentData)return <p>Loading receipt evidence…</p>
 const c=currentData.data
 return <div className="space-y-3"><p>Receipt {c.lot_number} · {c.receipt_status}. Original consumption {c.original_consumed}; accounted consumption {c.accounted_consumed}; on hand {c.on_hand}; reserved {c.reserved} {c.unit}. Affected batches: {c.affected_batch_ids.join(', ')}.</p>
 {c.pending?<p>A correction for this receipt already awaits review.</p>:<form onSubmit={submit} className="space-y-3">
  <Field name="corrected_consumed" label="Corrected ingredient consumption" type="number" min="0" max="999999.999" step="0.001"/>
  <Field name="observed_on_hand" label="Observed receipt quantity on hand" type="number" min="0" max="999999.999" step="0.001"/>
  <Field name="reason" label="Ingredient correction reason" maxLength={5000}/>
  <Field name="measurement_evidence" label="Ingredient measurement evidence" maxLength={5000}/>
  <Field name="source_evidence" label="Original ingredient source evidence" maxLength={5000}/>
  <Field name="receipt_count_evidence" label="Receipt physical-count evidence" maxLength={5000}/>
  <p>Use {c.unit}. Explain unrelated stock discrepancies separately. Submission holds the receipt; it does not apply a stock adjustment.</p>
  {error&&<p role="alert">{error}</p>}<Button disabled={isFetching||isLoading}>Save ingredient correction proposal</Button>
 </form>}</div>
}
function ConsumptionDecision({proposal,disabled}:{proposal:ConsumptionCorrectionProposal;disabled:boolean}){
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');try{await save({path:`consumption-corrections/${proposal.id}/decision`,body:Object.fromEntries(new FormData(e.currentTarget))}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <form onSubmit={submit} className="space-y-3"><Choice name="decision" label="Independent ingredient correction decision" options={['rejected','applied']}/><Field name="evidence" label="Ingredient correction review evidence" maxLength={5000}/><p>Application changes the receipt balance shown above and retains quarantine. It does not approve the preparation or release medication.</p>{error&&<p role="alert">{error}</p>}<Button disabled={disabled||isLoading}>Save ingredient correction decision</Button></form>
}
