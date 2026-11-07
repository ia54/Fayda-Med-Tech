"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {JointIngredient,JointIncidentProposal,useGetJointIncidentGroupQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function CompoundingJointReview({id}:{id:number}) {
 const {user}=useAuth();const [page,setPage]=useState(1);const {currentData,isFetching,isError,refetch}=useGetJointIncidentGroupQuery({id,page});const data=currentData?.data
 if(isError)return <p role="alert">Could not load joint reconciliation. <Button type="button" onClick={()=>refetch()}>Retry joint history</Button></p>
 if(!data)return <p>Loading joint reconciliation…</p>
 return <section className="space-y-3 border rounded p-3"><h4 className="font-semibold">Joint reconciliation {id} · {data.group.status}</h4><p className="whitespace-pre-wrap">{data.group.evidence}</p><p>Incidents: {data.members.map(m=>`${m.id} (${m.status})`).join(', ')}</p>
 <p>Accounting and unused-material custody are reviewed separately. Reconciliation does not release ingredients or authorize preparation or dispensing.</p>
 {data.proposals.data.map(p=><JointDecision key={p.id} groupId={id} proposal={p} authors={[data.group.created_by,...data.members.map(m=>m.created_by)]} refreshing={isFetching}/>)}
 {!data.proposals.data.length&&<p>No joint proposals recorded.</p>}
 {data.group.status==='pending'&&!data.pending&&user?.role==='pharmacist'&&user.id!==data.group.created_by&&!data.members.some(m=>m.created_by===user.id)&&<RejectJointGroup id={id} refreshing={isFetching}/>}
 {user?.role==='pharmacist'&&!data.pending&&['pending','accounted_custody_held'].includes(data.group.status)&&<JointProposalForm key={`${id}-${data.group.status}`} id={id} version={data.group.version} phase={data.group.status==='pending'?'accounting':'custody'} ingredients={data.ingredients} unused={data.group.status==='pending'?data.prior_unused:data.unused} refreshing={isFetching}/>}

 <div className="flex gap-3 items-center"><Button type="button" variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous joint proposals</Button><span>Page {page} / {data.proposals.last_page}</span><Button type="button" variant="outline" disabled={page>=data.proposals.last_page||isFetching} onClick={()=>setPage(page+1)}>Next joint proposals</Button></div>
 </section>
}
function JointDecision({groupId,proposal,authors,refreshing}:{groupId:number;proposal:JointIncidentProposal;authors:number[];refreshing:boolean}) {
 const {user}=useAuth();const [decision,setDecision]=useState('reject');const [error,setError]=useState('');const [success,setSuccess]=useState('');const [save,{isLoading}]=usePharmacyStockActionMutation()
 const independent=user?.role==='pharmacist'&&!authors.includes(user.id)&&user.id!==proposal.created_by
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');setSuccess('');const evidence=new FormData(e.currentTarget).get('review_evidence')
  try {await save({path:`incident-groups/${groupId}/${proposal.phase}/${proposal.id}/${decision}`,body:{evidence}}).unwrap();setSuccess('Independent decision retained.')}catch(e){setError(errorMessage(e))}
 }
 return <article className="space-y-3 border-t pt-3"><strong>{proposal.phase==='accounting'?'Accounting':'Unused-material custody'} proposal {proposal.id} · {proposal.status}</strong><p className="whitespace-pre-wrap">{proposal.evidence}</p>
 {proposal.proposal.map(line=><div key={line.allocation_id} className="space-y-1"><p>Incident {line.incident_id} · receipt {line.ingredient_lot_id} · allocation {line.allocation_id}</p>
 {line.mode==='already_accounted'&&<p>Previously accounted material; retained unused custody: {line.unused_retained} {line.unit}. Prior consumption is not deducted again.</p>}
 {line.accounting&&Object.entries(line.accounting.quantities).map(([key,value])=><p key={key}>{key.replaceAll('_',' ')}: {value} {line.unit}</p>)}
 {line.custody&&<><p>Retained unused: {line.custody.unused_quantity} {line.unit}</p><p>Return to quarantine: {line.custody.return_to_quarantine} {line.unit}</p><p>Completed unused disposal: {line.custody.disposed_unused} {line.unit}</p></>}
 <p className="whitespace-pre-wrap">Evidence: {line.evidence}</p></div>)}
 {proposal.review_evidence&&<p className="whitespace-pre-wrap">Reviewer {proposal.reviewed_by} · {proposal.reviewed_at}: {proposal.review_evidence}</p>}
 {success&&<p role="status">{success}</p>}
 {proposal.status==='pending'&&(independent?<form onSubmit={submit}><fieldset disabled={refreshing||isLoading} className="space-y-3"><label className="grid gap-1">Independent decision<select className="border rounded p-2 bg-background" value={decision} onChange={e=>setDecision(e.target.value)}><option value="reject">Reject proposal; keep stock held</option><option value="apply" disabled={proposal.proposal.some(line=>line.accounting?.unaccounted_remaining)}>Apply reviewed {proposal.phase==='accounting'?'accounting; retain custody holds':'custody; retain quarantine or recall'}</option></select></label><Field name="review_evidence" label="Independent review findings and evidence" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={refreshing||isLoading}>Record joint review decision</Button></fieldset></form>:<p>A different assigned pharmacist must review this proposal.</p>)}
 </article>
}

function JointProposalForm({id,version,phase,ingredients,unused,refreshing}:{id:number;version:number;phase:'accounting'|'custody';ingredients:JointIngredient[];unused:{allocation_id:number;unused_retained:string}[];refreshing:boolean}) {
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const request=useRef<{body:string;id:string}|null>(null)
 const fields=phase==='accounting'?[['additional_taken','Additional material taken'],['consumed','Consumed in preparation'],['unused_retained','Unused material retained'],['disposed_unused','Completed unused disposal'],['unaccounted','Unaccounted material']]:[['return_to_quarantine','Return to quarantine'],['disposed_unused','Completed unused disposal']]
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');const values=new FormData(e.currentTarget)
  const body={version,evidence:values.get('evidence'),ingredients:ingredients.map(line=>({allocation_id:line.allocation_id,...Object.fromEntries(fields.map(([key])=>[key,String(values.get(`${line.allocation_id}-${key}`))])),evidence:values.get(`${line.allocation_id}-evidence`)}))}
  const encoded=JSON.stringify(body);if(request.current?.body!==encoded)request.current={body:encoded,id:crypto.randomUUID()}
  try {await save({path:`incident-groups/${id}/${phase}`,body:{...body,request_id:request.current.id}}).unwrap()}catch(e){setError(errorMessage(e))}
 }
 return <form onSubmit={submit}><fieldset disabled={isLoading||refreshing} className="space-y-3"><legend className="font-semibold">Propose joint {phase==='accounting'?'quantity accounting':'unused-material custody'}</legend><p>Enter every amount explicitly. An unknown amount is not zero. {phase==='accounting'?'For previously accounted incidents, preserve the retained unused quantity and enter zero for additional consumption or disposal.':'Account for all retained unused material as returned to quarantine or documented completed unused disposal. Do not repeat earlier consumption or disposal.'}</p>
 {ingredients.map(line=><fieldset key={line.allocation_id} className="border rounded p-3 space-y-2"><legend>Incident {line.incident_id} · receipt {line.ingredient_lot_id}</legend><p>Original reservation: {line.reserved_quantity} {line.quantity_unit}</p>{(phase==='custody'||unused.some(row=>row.allocation_id===line.allocation_id))&&<p>Retained unused material: {unused.find(row=>row.allocation_id===line.allocation_id)?.unused_retained??'Unavailable'} {line.quantity_unit}</p>}{fields.map(([key,label])=><Field key={key} name={`${line.allocation_id}-${key}`} label={`${label} (${line.quantity_unit})`} type="number" min="0" max="999999.999" step="0.001"/>)}<Field name={`${line.allocation_id}-evidence`} label="Measurement and completed-event evidence" maxLength={5000}/></fieldset>)}
 <Field name="evidence" label="Joint investigation findings and evidence" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||refreshing}>Retain joint proposal for independent review</Button></fieldset></form>
}

function RejectJointGroup({id,refreshing}:{id:number;refreshing:boolean}) {
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>) {e.preventDefault();setError('');try {await save({path:`incident-groups/${id}/reject`,body:{evidence:new FormData(e.currentTarget).get('evidence')}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <details className="border rounded p-3"><summary>Reject this investigation group</summary><p>Use this when membership or source evidence has changed. Reject pending proposals first. Original incident holds remain in place.</p><form onSubmit={submit}><fieldset disabled={isLoading||refreshing} className="space-y-3"><Field name="evidence" label="Independent reason for rejecting the group" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||refreshing}>Reject group and retain incident holds</Button></fieldset></form></details>
}
