"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {CompoundingIncident,CompoundingCustody,useGetCompoundingCustodyQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
export function CompoundingCustodyReview({incident}:{incident:CompoundingIncident}) {
 const {user}=useAuth();const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetCompoundingCustodyQuery({id:incident.id,page})
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('')
 const request=useRef<{body:string;id:string}|null>(null)
 const history=currentData?.data.data||[]
 const canPropose=user?.role==='pharmacist'&&currentData?.incident.status==='accounted_custody_held'&&page===1&&!history.some(p=>p.status==='pending')&&!isError
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();const form=e.currentTarget;const fields=new FormData(form);setError('');setSuccess('')
  const body={version:currentData?.incident.version,evidence:fields.get('evidence'),ingredients:(currentData?.unused||[]).map(line=>({allocation_id:line.allocation_id,return_to_quarantine:fields.get(`${line.allocation_id}-return`),disposed_unused:fields.get(`${line.allocation_id}-disposed`),evidence:fields.get(`${line.allocation_id}-evidence`)}))}
  const encoded=JSON.stringify(body);if(request.current?.body!==encoded)request.current={body:encoded,id:crypto.randomUUID()}
  try {await save({path:`incidents/${incident.id}/custody`,body:{...body,request_id:request.current.id}}).unwrap();setSuccess('Custody proposal saved for another pharmacist to review. Stock remains unchanged.');form.reset()}catch(e){setError(errorMessage(e))}
 }
 return <section className="space-y-3 border-t pt-3"><h4 className="font-semibold">Unused ingredient custody</h4><p>Account for unused material retained after consumption accounting. Record only completed, evidenced events. Returned material remains quarantined; this review does not authorize disposal, product release or dispensing.</p>
 {success&&<p role="status">{success}</p>}
 {isError?<p role="alert">Could not load custody history. <Button type="button" onClick={()=>refetch()}>Retry custody history</Button></p>:!currentData?<p>Loading custody history…</p>:<>
 {history.map(proposal=><CustodyDecision key={proposal.id} incident={incident} proposal={proposal} refreshing={isFetching}/>)}
 {!history.length&&<p>No unused-material custody proposals recorded.</p>}
 {currentData.incident.status==='reconciled'&&<p role="status">Incident quantities and unused-material custody are reconciled. This does not release quarantined ingredients or authorize the original preparation.</p>}
 <div className="flex gap-3 items-center"><Button type="button" variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous custody proposals</Button><span>Page {page} / {currentData.data.last_page}</span><Button type="button" variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>setPage(page+1)}>Next custody proposals</Button></div>
 {canPropose&&<form onSubmit={submit}><fieldset disabled={isLoading||isFetching} className="space-y-3">{currentData.unused.map(line=><fieldset key={line.allocation_id} className="border rounded p-3 space-y-3"><legend>Receipt {line.ingredient_lot_id} · allocation {line.allocation_id}</legend><p>Unused material requiring custody: {line.unused_quantity} {line.unit}</p><Field name={`${line.allocation_id}-return`} label={`Returned to quarantine (${line.unit})`} type="number" min="0" max="999999.999" step="0.001"/><Field name={`${line.allocation_id}-disposed`} label={`Completed unused-material disposal (${line.unit})`} type="number" min="0" max="999999.999" step="0.001"/><Field name={`${line.allocation_id}-evidence`} label="Material identity, custody and completed-event evidence" maxLength={5000}/></fieldset>)}<Field name="evidence" label="Custody investigation findings" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button>Retain unused-material custody proposal</Button></fieldset></form>}
 </>}
 </section>
}
function CustodyDecision({incident,proposal,refreshing}:{incident:CompoundingIncident;proposal:CompoundingCustody;refreshing:boolean}) {
 const {user}=useAuth();const [save,{isLoading}]=usePharmacyStockActionMutation();const [decision,setDecision]=useState('reject');const [error,setError]=useState('')
 const independent=user?.role==='pharmacist'&&user.id!==proposal.created_by&&user.id!==incident.created_by
 async function submit(e:FormEvent<HTMLFormElement>) {e.preventDefault();setError('');try {await save({path:`incidents/${incident.id}/custody/${proposal.id}/${decision}`,body:{evidence:new FormData(e.currentTarget).get('review_evidence')}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <article className="border rounded p-3 space-y-3"><strong>Custody proposal {proposal.id} · {proposal.status}</strong><p className="whitespace-pre-wrap">{proposal.evidence}</p>{proposal.proposal.map(line=><div key={line.allocation_id}><p>Receipt {line.ingredient_lot_id} · retained unused: {line.custody.unused_quantity} {line.unit}</p><p>Returned to quarantine: {line.custody.return_to_quarantine} {line.unit}; completed disposal: {line.custody.disposed_unused} {line.unit}</p><p className="whitespace-pre-wrap">Evidence: {line.evidence}</p></div>)}{proposal.review_evidence&&<p className="whitespace-pre-wrap">Reviewer {proposal.reviewed_by} · {proposal.reviewed_at}: {proposal.review_evidence}</p>}
 {proposal.status==='pending'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||refreshing} className="space-y-3"><label className="grid gap-1">Custody review decision<select className="border rounded p-2 bg-background" value={decision} onChange={e=>setDecision(e.target.value)}><option value="reject">Reject proposal; preserve custody hold</option><option value="apply">Apply reviewed custody accounting; keep ingredients quarantined</option></select></label><Field name="review_evidence" label="Independent custody review findings and evidence" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button>Record custody review</Button></fieldset></form>:<p>A different assigned pharmacist must review this custody proposal.</p>)}
 </article>
}
