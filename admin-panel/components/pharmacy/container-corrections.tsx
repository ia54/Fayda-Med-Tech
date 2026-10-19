"use client"
import {FormEvent, useRef, useState} from 'react'
import {Button} from '@/components/ui/button'
import {useAuth} from '@/hooks/useAuth'
import {Field, errorMessage} from './fields'
import {CompoundBatch, ContainerCustodyContext, ContainerRecountFinding, useGetContainerRecountContextQuery, useGetContainerRecountHistoryQuery, useGetContainerRecountProposalQuery, useCreateContainerRecountProposalMutation, useDecideContainerRecountProposalMutation} from '@/store/api/pharmacyApiSlice'

function Evidence({name,label}:{name:string;label:string}) {
 return <label className="grid gap-1 text-sm">{label}<textarea name={name} required maxLength={5000} rows={3} className="border rounded p-2 bg-background"/></label>
}
function EvidenceFields({prefix,label}:{prefix:string;label:string}) {
 return <><Evidence name={`${prefix}_reason`} label={`${label}: correction reason`}/><Evidence name={`${prefix}_measurement`} label={`${label}: measured recount evidence`}/><Evidence name={`${prefix}_source`} label={`${label}: original-source evidence`}/></>
}
export function ContainerCorrections({batch}:{batch:CompoundBatch}) {
 const {user}=useAuth(); const id=batch.execution?.id??0
 const [page,setPage]=useState(1); const [selected,setSelected]=useState<number|null>(null); const [creating,setCreating]=useState(false)
 const q=useGetContainerRecountHistoryQuery({id,page},{skip:!id})
 if(!id)return null
 if(q.isError)return <p role="alert">Could not load container corrections. <Button onClick={()=>q.refetch()}>Retry container corrections</Button></p>
 if(!q.currentData)return <p>Loading container corrections…</p>
 const pending=q.currentData.pending||q.currentData.custody_pending
 return <section id="container-corrections" className="border-t pt-4 space-y-4">
  <h3 className="text-lg font-semibold">Container quantity corrections</h3>
  <p className="text-sm">Correct recorded quantities using measured evidence for every container and the unpackaged remainder. Original quantities and prior disposal stay in the history. This records a recount; it does not record a physical transfer, repackaging or release.</p>
  {q.currentData.data.data.map(p=><div key={p.id} className="flex flex-wrap justify-between gap-2 border-b pb-2"><p>Container correction {p.id} · {p.status}</p><Button variant="outline" disabled={q.isFetching} onClick={()=>{setSelected(p.id);setCreating(false)}}>Open container correction {p.id}</Button></div>)}
  {!q.currentData.data.total&&<p>No container quantity corrections.</p>}
  <div className="flex gap-3 items-center"><Button variant="outline" disabled={q.isFetching||page===1} onClick={()=>setPage(page-1)}>Previous container corrections</Button><span>Page {page} / {q.currentData.data.last_page}</span><Button variant="outline" disabled={q.isFetching||page>=q.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next container corrections</Button></div>
  {pending?<p>Resolve the pending yield correction, output custody or repackaging proposal before recording another correction.</p>:user?.role==='pharmacist'&&<Button variant="outline" disabled={q.isFetching} onClick={()=>{setCreating(!creating);setSelected(null)}}>{creating?'Close recount form':'Record container recount'}</Button>}
  {creating&&!pending&&<fieldset disabled={q.isFetching} className="min-w-0"><Context id={id} onDone={value=>{setCreating(false);setSelected(value);setPage(1)}}/></fieldset>}
  {selected&&<Detail key={selected} id={selected} batch={batch}/>}
 </section>
}
function Context({id,onDone}:{id:number;onDone:(id:number)=>void}) {
 const q=useGetContainerRecountContextQuery(id)
 if(q.isError)return <p role="alert">Container recount evidence is unavailable. {errorMessage(q.error)} <Button onClick={()=>q.refetch()}>Retry recount evidence</Button></p>
 if(!q.currentData)return <p>Loading current container quantities…</p>
 return <fieldset disabled={q.isFetching} className="min-w-0"><Create context={q.currentData.data} onDone={onDone}/></fieldset>
}
function FindingFields({prefix,label,quantity,unit}:{prefix:string;label:string;quantity:string;unit:string}) {
 return <fieldset className="border rounded p-3 space-y-3"><legend>{label}</legend><p>Previously held: {quantity} {unit}</p><Field name={`${prefix}_quantity`} label={`${label}: observed quantity (${unit})`}/><EvidenceFields prefix={prefix} label={label}/></fieldset>
}
function Create({context,onDone}:{context:ContainerCustodyContext;onDone:(id:number)=>void}) {
 const source=useRef(context).current; const request=useRef<{payload:string;id:string}|null>(null)
 const [save,{isLoading}]=useCreateContainerRecountProposalMutation(); const [error,setError]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');const f=new FormData(e.currentTarget);const v=(key:string)=>String(f.get(key)??'')
  const evidence=(prefix:string)=>({reason:v(`${prefix}_reason`),measurement_evidence:v(`${prefix}_measurement`),source_evidence:v(`${prefix}_source`)})
  const finding=(prefix:string):ContainerRecountFinding=>({observed_quantity:v(`${prefix}_quantity`),...evidence(prefix)})
  const body={id:source.execution_id,source_hash:source.source_hash,decision:{unit:source.balance.unit,corrected_yield:v('corrected_yield'),containers:source.balance.containers.map((c,i)=>({identifier:c.identifier,...finding(`c${i}`)})),unpackaged:finding('unpackaged'),...evidence('overall')}}
  const payload=JSON.stringify(body);if(request.current?.payload!==payload)request.current={payload,id:crypto.randomUUID()}
  try{const result=await save({...body,request_id:request.current.id}).unwrap();onDone(result.data.id)}catch(e){setError(errorMessage(e))}
 }
 const changed=context.source_hash!==source.source_hash
 return <form onSubmit={submit} className="border rounded p-4 space-y-3"><h4 className="font-semibold">New container recount correction</h4>
  <p>Packaging proposal {source.packaging_proposal_id}. Accounted yield: {source.balance.recorded_yield} {source.balance.unit}. Prior disposal: {source.balance.previously_disposed} {source.balance.unit}.</p>
  <p className="text-sm">Enter exact measured quantities, including zero, with up to three decimal places. Corrected yield must include every observed container quantity, the unpackaged remainder and all prior disposal. Provide evidence even for unchanged quantities. An independent pharmacist must review before balances change.</p>
  {changed&&<p role="alert">Source evidence changed. Preserve your notes, then close and reopen this form.</p>}
  <fieldset disabled={isLoading||changed} className="space-y-3 min-w-0">
   {source.balance.containers.map((c,i)=><FindingFields key={c.identifier} prefix={`c${i}`} label={`Container ${c.identifier}`} quantity={c.quantity} unit={source.balance.unit}/>)}
   <FindingFields prefix="unpackaged" label="Unpackaged remainder" quantity={source.balance.unpackaged_quantity} unit={source.balance.unit}/>
   <Field name="corrected_yield" label={`Corrected total yield including prior disposal (${source.balance.unit})`}/><EvidenceFields prefix="overall" label="Overall recount"/>
   {error&&<p role="alert">{error}</p>}<Button>Retain container recount</Button>
  </fieldset>
 </form>
}
function Finding({label,value,unit}:{label:string;value:ContainerRecountFinding & {previous_quantity:string;change_direction:string;change_quantity:string};unit:string}) {
 return <div className="border-b pb-2 space-y-1"><strong>{label}</strong><p>Previous: {value.previous_quantity} · Observed: {value.observed_quantity} {unit} · {value.change_direction}: {value.change_quantity} {unit}</p><p className="whitespace-pre-wrap break-words">Reason: {value.reason}</p><p className="whitespace-pre-wrap break-words">Measured evidence: {value.measurement_evidence}</p><p className="whitespace-pre-wrap break-words">Original-source evidence: {value.source_evidence}</p></div>
}
function Detail({id,batch}:{id:number;batch:CompoundBatch}) {
 const {user}=useAuth(); const q=useGetContainerRecountProposalQuery(id); const [save,{isLoading}]=useDecideContainerRecountProposalMutation(); const [error,setError]=useState(''); const [success,setSuccess]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');setSuccess('');const f=new FormData(e.currentTarget)
  try{await save({id,decision:String(f.get('decision')) as 'applied'|'rejected',evidence:String(f.get('evidence'))}).unwrap();setSuccess('Container correction decision retained. Output remains quarantined; no transfer or release authorized.')}catch(e){setError(errorMessage(e))}
 }
 if(q.isError)return <p role="alert">Could not load container recount. <Button onClick={()=>q.refetch()}>Retry recount detail</Button></p>
 if(!q.currentData)return <p>Loading container recount…</p>
 const p=q.currentData.data; const v=p.proposal
 const independent=Number(user?.id)!==p.created_by&&Number(user?.id)!==batch.execution?.created_by&&!batch.execution?.addenda.some(a=>a.created_by===Number(user?.id))
 return <article className="border rounded p-4 space-y-3"><h4 className="font-semibold">Container correction {p.id} · {p.status}</h4><p>Packaging proposal {p.packaging_proposal_id} · Recorded by user {p.created_by} · {p.created_at}</p>
  <p>Original yield: {v.original_yield} · Previous accounted yield: {v.previous_accounted_yield} · Corrected yield: {v.corrected_yield} {v.unit}</p><p>Prior disposal retained: {v.previously_disposed} · Corrected held output: {v.corrected_held_output} {v.unit}</p>
  {v.containers.map(c=><Finding key={c.identifier} label={`Container ${c.identifier}`} value={c} unit={v.unit}/>)}<Finding label="Unpackaged remainder" value={v.unpackaged} unit={v.unit}/>
  <p className="whitespace-pre-wrap break-words">Overall reason: {v.reason}</p><p className="whitespace-pre-wrap break-words">Measured evidence: {v.measurement_evidence}</p><p className="whitespace-pre-wrap break-words">Original-source evidence: {v.source_evidence}</p>
  {p.reviewed_by&&<><p>Reviewed by user {p.reviewed_by} · {p.reviewed_at}</p><p className="whitespace-pre-wrap break-words">{p.review_evidence}</p></>}
  {p.status==='pending'&&user?.role==='pharmacist'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||q.isFetching} className="space-y-3"><label className="grid gap-1 text-sm">Container correction decision<select name="decision" defaultValue="rejected" className="border rounded p-2 bg-background"><option value="rejected">Reject proposal; preserve recount</option><option value="applied">Apply measured quantity correction</option></select></label><Evidence name="evidence" label="Independent recount review findings and evidence"/>{error&&<p role="alert">{error}</p>}<Button>Save recount decision</Button></fieldset></form>:<p>Another pharmacist independent of this recount and preparation must review this proposal.</p>)}
  {success&&<p role="status">{success}</p>}
 </article>
}
