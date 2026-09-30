"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {CompoundingIncident,CompoundingReconciliation,useGetCompoundingReconciliationsQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {CompoundingCustodyReview} from './compounding-custody'
const quantities=[['additional_taken','Additional material taken beyond the reservation'],['consumed','Consumed in preparation'],['unused_retained','Unused material still in custody'],['disposed_unused','Unused material with documented completed disposal'],['unaccounted','Material not yet accounted for']] as const
export function CompoundingReconciliationReview({incident}:{incident:CompoundingIncident}) {
 const {user}=useAuth();const [page,setPage]=useState(1);const {currentData,isFetching,isError,refetch}=useGetCompoundingReconciliationsQuery({id:incident.id,page})
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('');const request=useRef<{body:string;id:string}|null>(null)
 const history=currentData?.data.data||[];const state=currentData?.incident
 const canPropose=user?.role==='pharmacist'&&state?.status==='unresolved'&&page===1&&!history.some(p=>p.status==='pending')&&!isError
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();const form=e.currentTarget;const values=new FormData(form);setError('');setSuccess('')
  const body={version:state?.version,evidence:values.get('evidence'),ingredients:incident.ingredients.map(line=>({allocation_id:line.allocation_id,...Object.fromEntries(quantities.map(([key])=>[key,String(values.get(`${line.allocation_id}-${key}`))])),evidence:values.get(`${line.allocation_id}-evidence`)}))}
  const encoded=JSON.stringify(body);if(request.current?.body!==encoded)request.current={body:encoded,id:crypto.randomUUID()}
  try {await save({path:`incidents/${incident.id}/reconciliations`,body:{...body,request_id:request.current.id}}).unwrap();setSuccess('Proposal retained for independent review. Stock and custody holds are unchanged.');form.reset()}catch(e){setError(errorMessage(e))}
 }
 return <section className="space-y-3 border-t pt-3"><h4 className="font-semibold">Independent quantity reconciliation</h4><p className="text-sm">Account for the original reservation plus any additional material actually taken. Use separate amounts for consumed, retained unused, disposed unused and unaccounted material. Do not count disposal of already-consumed ingredients a second time. Enter zero explicitly only where supported by evidence.</p>
 <p className="text-sm">Review applies confirmed consumption to the ledger once. Unused material stays reserved and all custody holds remain. This does not authorize disposal, preparation, product release or dispensing.</p>
 {success&&<p role="status">{success}</p>}{isError?<p role="alert">Could not load reconciliation history. <Button type="button" onClick={()=>refetch()}>Retry reconciliation history</Button></p>:!currentData?<p>Loading reconciliation history…</p>:<>
 {history.map(proposal=><Decision key={proposal.id} incident={incident} proposal={proposal} refreshing={isFetching}/>)}
 {!history.length&&<p>No reconciliation proposals recorded.</p>}
 <div className="flex gap-3 items-center"><Button type="button" variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous proposals</Button><span>Page {page} / {currentData.data.last_page}</span><Button type="button" variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>setPage(page+1)}>Next proposals</Button></div>
 {state?.status==='accounted_custody_held'&&<p role="status">Ledger accounting is recorded. Final custody resolution remains outstanding; ingredients and output are not released.</p>}
 {canPropose&&<form onSubmit={submit}><fieldset disabled={isLoading||isFetching} className="space-y-3">{incident.ingredients.map(line=><fieldset key={line.allocation_id} className="border rounded p-3 space-y-3"><legend>Ingredient {line.ingredient_key} · receipt {line.ingredient_lot_id}</legend><p>Original reservation: {line.reserved_quantity} {line.quantity_unit}. Original observed consumption: {line.observed_quantity===null?'Unknown':`${line.observed_quantity} ${line.quantity_unit}`}.</p>{quantities.map(([key,label])=><Field key={key} name={`${line.allocation_id}-${key}`} label={`${label} (${line.quantity_unit})`} type="number" min="0" max="999999.999" step="0.001"/>)}<Field name={`${line.allocation_id}-evidence`} label="Measurement, material custody and completed-event evidence" maxLength={5000}/></fieldset>)}<Field name="evidence" label="Investigation findings and reconciliation evidence" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||isFetching}>Retain reconciliation proposal</Button></fieldset></form>}
 </>}
 <CompoundingCustodyReview incident={incident}/>
 </section>
}
function Decision({incident,proposal,refreshing}:{incident:CompoundingIncident;proposal:CompoundingReconciliation;refreshing:boolean}) {
 const {user}=useAuth();const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [decision,setDecision]=useState('reject')
 const independent=user?.role==='pharmacist'&&user.id!==proposal.created_by&&user.id!==incident.created_by
 async function submit(e:FormEvent<HTMLFormElement>) {e.preventDefault();setError('');try {await save({path:`incidents/${incident.id}/reconciliations/${proposal.id}/${decision}`,body:{evidence:new FormData(e.currentTarget).get('review_evidence')}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <article className="border rounded p-3 space-y-3"><strong>Proposal {proposal.id} · {proposal.status}</strong><p className="whitespace-pre-wrap">{proposal.evidence}</p>{proposal.proposal.map(line=><div key={line.allocation_id}><p>Receipt {line.ingredient_lot_id} · original reservation {line.accounting.quantities.reserved_quantity} {line.unit}</p>{quantities.map(([key,label])=><p key={key}>{label}: {line.accounting.quantities[key]} {line.unit}</p>)}<p className="whitespace-pre-wrap">Evidence: {line.evidence}</p></div>)}{proposal.review_evidence&&<p className="whitespace-pre-wrap">Reviewer {proposal.reviewed_by} · {proposal.reviewed_at}: {proposal.review_evidence}</p>}
 {proposal.status==='pending'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||refreshing} className="space-y-3"><label className="grid gap-1">Review decision<select className="border rounded p-2 bg-background" value={decision} onChange={e=>setDecision(e.target.value)}><option value="reject">Reject proposal; retain custody hold</option><option value="apply" disabled={proposal.proposal.some(line=>line.accounting.unaccounted_remaining)}>Apply reviewed accounting; retain custody hold</option></select></label><Field name="review_evidence" label="Independent review findings and evidence" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||refreshing}>Record review decision</Button></fieldset></form>:<p>A different assigned pharmacist must review this proposal.</p>)}
 </article>
}
