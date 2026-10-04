"use client"
import {FormEvent,useRef,useState} from 'react'
import {Button} from '@/components/ui/button'
import {useAuth} from '@/hooks/useAuth'
import {errorMessage} from './fields'
import {CompoundBatch,SuitabilityContext,SuitabilityAssessment,useGetSuitabilityHistoryQuery,useGetSuitabilityContextQuery,useGetSuitabilityProposalQuery,useCreateSuitabilityProposalMutation,useDecideSuitabilityProposalMutation} from '@/store/api/pharmacyApiSlice'
function Evidence({name,label}:{name:string;label:string}){return <label className="grid gap-1">{label}<textarea className="border rounded p-2 bg-background" name={name} required maxLength={5000} rows={3}/></label>}
export function ContainerSuitability({batch}:{batch:CompoundBatch}){
 const {user}=useAuth();const id=batch.execution?.id??0;const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null);const [creating,setCreating]=useState(false)
 const q=useGetSuitabilityHistoryQuery({id,page},{skip:!id});const latest=useGetSuitabilityHistoryQuery({id,page:1},{skip:!id})
 if(!id)return null
 if(q.isError||latest.isError)return <p role="alert">Could not load suitability history. <Button onClick={()=>{q.refetch();latest.refetch()}}>Retry suitability history</Button></p>
 if(!q.currentData||!latest.currentData)return <p>Loading suitability history…</p>
 const last=latest.currentData.data.data[0];const busy=q.isFetching||latest.isFetching
 return <section className="border-t pt-4 space-y-4"><h3 className="text-lg font-semibold">Container suitability findings</h3><p>Compare each nonempty container and its storage with the reviewed dating evidence. Retaining or reviewing findings does not authorize medication release.</p>
 {q.currentData.data.data.map(p=><div key={p.id} className="flex justify-between gap-3"><p>Suitability {p.id} · {p.status}</p><Button disabled={busy} variant="outline" onClick={()=>{setSelected(p.id);setCreating(false)}}>Open suitability {p.id}</Button></div>)}
 {!q.currentData.data.total&&<p>No suitability findings retained.</p>}
 <div className="flex gap-3 items-center"><Button disabled={busy||page===1} onClick={()=>setPage(page-1)}>Previous suitability records</Button><span>Page {page} / {q.currentData.data.last_page}</span><Button disabled={busy||page>=q.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next suitability records</Button></div>
 {user?.role==='pharmacist'&&(last?.status==='pending'?<p>Review or reject the pending findings before recording a replacement.</p>:<Button disabled={busy} onClick={()=>{setCreating(!creating);setSelected(null)}}>{creating?'Close suitability form':'Record suitability findings'}</Button>)}
 {creating&&last?.status!=='pending'&&<fieldset disabled={busy}><Context execution={id} previous={last?.id??null} onDone={v=>{setCreating(false);setSelected(v);setPage(1)}}/></fieldset>}
 {selected&&<Detail key={selected} id={selected} batch={batch}/>}</section>
}
function Context({execution,previous,onDone}:{execution:number;previous:number|null;onDone:(id:number)=>void}){
 const q=useGetSuitabilityContextQuery(execution)
 if(q.isError)return <p role="alert">Current suitability evidence is unavailable. {errorMessage(q.error)} <Button onClick={()=>q.refetch()}>Retry suitability prerequisites</Button></p>
 if(!q.currentData)return <p>Checking current container and dating evidence…</p>
 return <fieldset disabled={q.isFetching}><Create context={q.currentData.data} previous={previous} onDone={onDone}/></fieldset>
}
function Create({context,previous,onDone}:{context:SuitabilityContext;previous:number|null;onDone:(id:number)=>void}){
 const [source]=useState(context);const [prior]=useState(previous);const [save,{isLoading}]=useCreateSuitabilityProposalMutation();const [error,setError]=useState('');const request=useRef<{body:string;id:string}|null>(null)
 const stale=source.source_hash!==context.source_hash||prior!==previous
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');if(stale)return;const f=new FormData(e.currentTarget);const proposal:SuitabilityAssessment[]=source.containers.map((c,i)=>({identifier:c.identifier,outcome:String(f.get(`outcome-${i}`)) as SuitabilityAssessment['outcome'],container_evidence:String(f.get(`container-${i}`)),storage_evidence:String(f.get(`storage-${i}`)),dating_scope_evidence:String(f.get(`dating-${i}`))}));const body={id:source.execution_id,previous_id:prior,source_hash:source.source_hash,proposal};const serialized=JSON.stringify(body);if(request.current?.body!==serialized)request.current={body:serialized,id:crypto.randomUUID()};try{const result=await save({...body,request_id:request.current.id}).unwrap();onDone(result.data.id)}catch(err){setError(errorMessage(err))}}
 const d=source.dating_evidence
 return <form onSubmit={submit} className="space-y-4"><h4 className="font-semibold">New suitability findings</h4><p>Reviewed dating proposal {source.dating_proposal_id}: {d.prepared_at} → {d.proposed_bud_at} ({d.timezone})</p><p>Dating container scope: {d.container_reference}</p><p>Dating storage: {d.storage_conditions}</p><p>Basis: {d.basis_reference}</p><p>Rationale: {d.rationale}</p><p>Unpackaged remainder: {source.unpackaged_quantity} {source.unit}. This assessment does not package it.</p>{source.empty_containers.map(c=><p key={c.identifier}>Empty retained container: {c.identifier}</p>)}
 {stale&&<p role="alert">Source evidence changed. Close and reopen this form to compare the current evidence. Your current input is preserved.</p>}
 <fieldset disabled={isLoading||stale} className="space-y-4">{source.containers.map((c,i)=><fieldset key={c.identifier} className="border rounded p-4 space-y-3"><legend>Container {c.identifier}</legend><p>{c.quantity} {source.unit} · {c.container_reference}</p><p>Recorded storage: {c.storage_reference}</p><label className="grid gap-1">{c.identifier}: suitability outcome<select name={`outcome-${i}`} required defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select an assessment</option><option value="suitable">Suitable based on retained evidence</option><option value="unsuitable">Unsuitable; follow-up required</option><option value="not_assessed">Not assessed; follow-up required</option></select></label><Evidence name={`container-${i}`} label={`${c.identifier}: container assessment evidence`}/><Evidence name={`storage-${i}`} label={`${c.identifier}: storage assessment evidence`}/><Evidence name={`dating-${i}`} label={`${c.identifier}: dating scope comparison evidence`}/></fieldset>)}{error&&<p role="alert">{error}</p>}<Button disabled={!source.containers.length}>Retain suitability findings</Button></fieldset></form>
}
function Detail({id,batch}:{id:number;batch:CompoundBatch}){
 const {user}=useAuth();const q=useGetSuitabilityProposalQuery(id);const [save,{isLoading}]=useDecideSuitabilityProposalMutation();const [error,setError]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');const f=new FormData(e.currentTarget);try{await save({id,decision:String(f.get('decision')) as 'reviewed'|'rejected',evidence:String(f.get('evidence'))}).unwrap()}catch(err){setError(errorMessage(err))}}
 if(q.isError)return <p role="alert">Could not load suitability evidence. <Button onClick={()=>q.refetch()}>Retry suitability detail</Button></p>
 if(!q.currentData)return <p>Loading suitability detail…</p>
 const p=q.currentData.data;const independent=Number(user?.id)!==p.created_by&&Number(user?.id)!==batch.execution?.created_by&&!batch.execution?.addenda.some(a=>a.created_by===Number(user?.id))
 return <article className="border rounded p-4 space-y-3"><h4 className="font-semibold">Suitability {p.id} · {p.status}</h4><p>Author {p.created_by} · Dating proposal {p.dating_proposal_id}</p>{p.proposal.requires_follow_up&&<p role="alert">Unsuitable or unassessed findings remain unresolved. Documentary review does not clear them.</p>}{p.proposal.containers.map(({container:c,assessment:a})=><div key={c.identifier} className="border-b pb-3"><strong>{c.identifier} · {a.outcome}</strong><p>Retained quantity: {c.quantity}</p><p>Container: {c.container_reference}</p><p>Storage: {c.storage_reference}</p><p>{a.container_evidence}</p><p>{a.storage_evidence}</p><p>{a.dating_scope_evidence}</p></div>)}{p.reviewed_by&&<p>Reviewed by {p.reviewed_by} · {p.reviewed_at} · {p.review_evidence}</p>}
 {p.status==='pending'&&user?.role==='pharmacist'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||q.isFetching} className="space-y-3"><label className="grid gap-1">Suitability review decision<select name="decision" defaultValue="rejected" className="border rounded p-2 bg-background"><option value="rejected">Reject; preserve findings</option><option value="reviewed">Retain independent documentary review</option></select></label><Evidence name="evidence" label="Independent suitability review evidence"/>{error&&<p role="alert">{error}</p>}<Button>Save suitability review</Button></fieldset></form>:<p>Another pharmacist independent of these findings and preparation must review.</p>)}<p>No medication release is authorized.</p></article>
}
