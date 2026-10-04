"use client"
import {FormEvent,useRef,useState} from 'react'
import {Button} from '@/components/ui/button'
import {useAuth} from '@/hooks/useAuth'
import {errorMessage} from './fields'
import {CompoundBatch,BatchQualityContext,BatchQualityObservation,useGetQualityProtocolsQuery,useGetBatchQualityContextQuery,useGetBatchQualityHistoryQuery,useGetBatchQualityResultQuery,useCreateBatchQualityResultMutation,useDecideBatchQualityResultMutation} from '@/store/api/pharmacyApiSlice'

function Evidence({name,label}:{name:string;label:string}){return <label className="grid gap-1 text-sm">{label}<textarea name={name} required maxLength={5000} rows={3} className="border rounded p-2 bg-background"/></label>}
export function BatchQuality({batch}:{batch:CompoundBatch}){
 const {user}=useAuth();const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null);const [creating,setCreating]=useState(false)
 const id=batch.execution?.id??0
 const history=useGetBatchQualityHistoryQuery({id,page},{skip:!id});const latest=useGetBatchQualityHistoryQuery({id,page:1},{skip:!id})
 if(!id)return null
 if(history.isError||latest.isError)return <p role="alert">Could not load batch quality history. <Button onClick={()=>{history.refetch();latest.refetch()}}>Retry batch quality history</Button></p>
 if(!history.currentData||!latest.currentData)return <p>Loading batch quality history…</p>
 const last=latest.currentData.data.data[0];const busy=history.isFetching||latest.isFetching
 return <section className="border-t pt-4 space-y-4"><h3 className="text-lg font-semibold">Batch quality observations</h3><p className="text-sm">Retain results against a reviewed protocol. Independent review confirms the documentary record, including failed or unassessed checks. It does not approve quality, assign a beyond-use date or release medication.</p>
 {history.currentData.data.data.map(r=><div key={r.id} className="flex flex-wrap justify-between gap-2 border-b pb-2"><p>Quality record {r.id} · {r.status} · {r.created_at}</p><Button variant="outline" disabled={busy} onClick={()=>{setSelected(r.id);setCreating(false)}}>Open quality results {r.id}</Button></div>)}
 {!history.currentData.data.total&&<p>No retained quality observations.</p>}
 <div className="flex flex-wrap items-center gap-3"><Button variant="outline" disabled={busy||page===1} onClick={()=>setPage(page-1)}>Previous quality records</Button><span>Page {page} / {history.currentData.data.last_page}</span><Button variant="outline" disabled={busy||page>=history.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next quality records</Button></div>
 {user?.role==='pharmacist'&&(last?.status==='pending'?<p>A pending quality record needs independent review or rejection before a replacement can be recorded.</p>:<Button variant="outline" disabled={busy} onClick={()=>{setCreating(!creating);setSelected(null)}}>{creating?'Close quality observations':'Record quality observations'}</Button>)}
 {creating&&<fieldset disabled={busy} className="min-w-0"><SelectProtocol batch={batch} previous={last?.id??null} onDone={newId=>{setCreating(false);setSelected(newId);setPage(1)}}/></fieldset>}
 {selected&&<QualityDetail key={selected} id={selected} batch={batch}/>}
 </section>
}
function SelectProtocol({batch,previous,onDone}:{batch:CompoundBatch;previous:number|null;onDone:(id:number)=>void}){
 const [protocol,setProtocol]=useState('');const q=useGetQualityProtocolsQuery({page:1,location_id:batch.location_id,formulation_id:batch.formulation_id,status:'reviewed'})
 if(q.isError)return <p role="alert">Could not load reviewed protocols. <Button onClick={()=>q.refetch()}>Retry reviewed protocols</Button></p>
 if(!q.currentData)return <p>Loading reviewed protocols…</p>
 return <div className="space-y-3"><label className="grid gap-1 text-sm">Batch quality protocol<select value={protocol} disabled={q.isFetching} onChange={e=>setProtocol(e.target.value)} className="border rounded p-2 bg-background"><option value="">Select reviewed protocol</option>{q.currentData.data.data.map(p=><option key={p.id} value={p.id}>Protocol {p.id} · Revision {p.revision_number}</option>)}</select></label>{!q.currentData.data.total&&<p>Create and independently review a protocol under this formulation and location first.</p>}{protocol&&q.currentData.data.data.some(p=>p.id===Number(protocol))&&<QualityContext key={protocol} execution={batch.execution!.id} protocol={Number(protocol)} previous={previous} onDone={onDone}/>}</div>
}
function QualityContext({execution,protocol,previous,onDone}:{execution:number;protocol:number;previous:number|null;onDone:(id:number)=>void}){
 const q=useGetBatchQualityContextQuery({id:execution,protocol_id:protocol})
 if(q.isError)return <p role="alert">Could not prepare current batch evidence. {errorMessage(q.error)} <Button onClick={()=>q.refetch()}>Retry batch evidence</Button></p>
 if(!q.currentData)return <p>Loading batch evidence…</p>
 return <fieldset disabled={q.isFetching} className="min-w-0"><QualityCreate context={q.currentData.data} previous={previous} onDone={onDone}/></fieldset>
}
function QualityCreate({context,previous,onDone}:{context:BatchQualityContext;previous:number|null;onDone:(id:number)=>void}){
 // Freeze the reviewed source at form-open; a changed source must never silently rebase entered observations.
 const initial=useRef({context,previous}).current;const request=useRef<{payload:string;id:string}|null>(null)
 const [save,{isLoading}]=useCreateBatchQualityResultMutation();const [error,setError]=useState('')
 const source=initial.context
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');const f=new FormData(e.currentTarget);const value=(key:string)=>String(f.get(key)??'')
 const body={id:source.execution_id,protocol_id:source.protocol_id,previous_id:initial.previous,source_hash:source.source_hash,evidence:value('evidence'),results:source.protocol_record.requirements.map(r=>({key:r.key,outcome:value(`outcome_${r.key}`) as BatchQualityObservation['outcome'],observation:value(`observation_${r.key}`),evidence_reference:value(`reference_${r.key}`)}))}
 const payload=JSON.stringify(body);if(request.current?.payload!==payload)request.current={payload,id:crypto.randomUUID()}
 try{const r=await save({...body,request_id:request.current.id}).unwrap();onDone(r.data.id)}catch(e){setError(errorMessage(e))}}
 return <form onSubmit={submit} className="border rounded p-4 space-y-3"><h4 className="font-semibold">Record batch quality results</h4><p className="break-words">{source.protocol_record.reference} · {source.protocol_record.revision}</p>
 {source.batch_hold&&<p role="alert">This batch has an unresolved hold. Observations can be retained; recording them will not clear the hold.</p>}
 {context.source_hash!==source.source_hash&&<p role="alert">Batch evidence changed while this form was open. Close and reopen the form after comparing the batch records. Your observations remain here for reference.</p>}
 <fieldset disabled={isLoading||context.source_hash!==source.source_hash} className="space-y-4 min-w-0">{source.protocol_record.requirements.map(r=><fieldset key={r.key} className="border rounded p-3 space-y-2"><legend>{r.label}</legend><p className="whitespace-pre-wrap break-words">Criterion: {r.criterion}</p><p className="whitespace-pre-wrap break-words">Method: {r.method_reference}</p><label className="grid gap-1 text-sm">{r.label} result<select required name={`outcome_${r.key}`} defaultValue="" className="border rounded p-2 bg-background"><option value="">Select observed outcome</option><option value="pass">Pass</option><option value="fail">Fail</option><option value="not_assessed">Not assessed</option></select></label><Evidence name={`observation_${r.key}`} label={`${r.label} observation`}/><Evidence name={`reference_${r.key}`} label={`${r.label} result evidence reference`}/></fieldset>)}<Evidence name="evidence" label="Batch quality supporting evidence"/>{error&&<p role="alert">{error}</p>}<Button>Retain quality observations</Button></fieldset></form>
}
function QualityDetail({id,batch}:{id:number;batch:CompoundBatch}){
 const {user}=useAuth();const q=useGetBatchQualityResultQuery(id);const [save,{isLoading}]=useDecideBatchQualityResultMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');setSuccess('');const f=new FormData(e.currentTarget);try{await save({id,decision:String(f.get('decision')) as 'reviewed'|'rejected',evidence:String(f.get('evidence'))}).unwrap();setSuccess('Quality decision retained. Output remains quarantined.')}catch(e){setError(errorMessage(e))}}
 if(q.isError)return <p role="alert">Could not load retained quality evidence. <Button onClick={()=>q.refetch()}>Retry quality result detail</Button></p>
 if(!q.currentData)return <p>Loading retained results…</p>
 const r=q.currentData.data;const independent=Number(user?.id)!==r.created_by&&Number(user?.id)!==batch.execution?.created_by&&!batch.execution?.addenda.some(a=>Number(user?.id)===a.created_by)
 return <article className="border rounded p-4 space-y-3"><h4 className="font-semibold">Quality record {r.id} · {r.status}</h4><p>Recorded by user {r.created_by} · {r.created_at}</p><p className="break-words">{r.protocol_record.reference} · {r.protocol_record.revision}</p>{r.results.requires_follow_up&&<p role="alert">Failed or unassessed observations require follow-up. Documentary review does not resolve these findings.</p>}{r.results.evidence.map(e=><div key={e.result.key} className="border-b pb-3 space-y-1"><strong>{e.requirement.label} · {e.result.outcome.replaceAll('_',' ')}</strong><p className="whitespace-pre-wrap break-words">Criterion: {e.requirement.criterion}</p><p className="whitespace-pre-wrap break-words">Method: {e.requirement.method_reference}</p><p className="whitespace-pre-wrap break-words">Observation: {e.result.observation}</p><p className="whitespace-pre-wrap break-words">Evidence: {e.result.evidence_reference}</p></div>)}<p className="whitespace-pre-wrap break-words">Supporting evidence: {r.evidence}</p>{r.reviewed_by&&<><p>Reviewed by user {r.reviewed_by} · {r.reviewed_at}</p><p className="whitespace-pre-wrap break-words">{r.review_evidence}</p></>}
 {r.status==='pending'&&user?.role==='pharmacist'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||q.isFetching} className="space-y-3"><p className="text-sm">Review requires independence from the observations, preparation and preparation addenda. The server rechecks current source evidence before retaining review.</p><label className="grid gap-1 text-sm">Batch quality decision<select name="decision" defaultValue="rejected" className="border rounded p-2 bg-background"><option value="rejected">Reject record for replacement</option><option value="reviewed">Record independent documentary review</option></select></label><Evidence name="evidence" label="Batch quality review evidence"/>{error&&<p role="alert">{error}</p>}<Button>Save batch quality decision</Button></fieldset></form>:<p>Another pharmacist independent of preparation and these observations must review this record.</p>)}{success&&<p role="status">{success}</p>}</article>
}
