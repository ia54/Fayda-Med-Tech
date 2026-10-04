"use client"
import {FormEvent,useRef,useState} from 'react'
import {Button} from '@/components/ui/button'
import {useAuth} from '@/hooks/useAuth'
import {Field,errorMessage} from './fields'
import {CompoundBatch,RepackagingContext,RepackagingQuantity,useGetRepackagingContextQuery,useGetRepackagingHistoryQuery,useGetRepackagingProposalQuery,useCreateRepackagingProposalMutation,useDecideRepackagingProposalMutation} from '@/store/api/pharmacyApiSlice'
const UNPACKAGED='__UNPACKAGED__'
function Evidence({name,label}:{name:string;label:string}){return <label className="grid gap-1 text-sm">{label}<textarea name={name} required maxLength={5000} rows={3} className="border rounded p-2 bg-background"/></label>}
export function ContainerRepackaging({batch}:{batch:CompoundBatch}){
 const {user}=useAuth();const id=batch.execution?.id??0;const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null);const [creating,setCreating]=useState(false)
 const q=useGetRepackagingHistoryQuery({id,page},{skip:!id})
 if(!id)return null
 if(q.isError)return <p role="alert">Could not load repackaging history. <Button onClick={()=>q.refetch()}>Retry repackaging history</Button></p>
 if(!q.currentData)return <p>Loading repackaging history…</p>
 return <section id="container-repackaging" className="border-t pt-4 space-y-4"><h3 className="text-lg font-semibold">Container repackaging</h3>
  <p className="text-sm">Record measured movements within this preparation and the final contents of every container. Empty original containers remain traceable. Repackaging does not change yield or prior disposal, assign a new beyond-use date, clear clinical holds or authorize dispensing.</p>
  {q.currentData.data.data.map(p=><div key={p.id} className="flex flex-wrap justify-between gap-2 border-b pb-2"><p>Repackaging {p.id} · {p.status}</p><Button variant="outline" disabled={q.isFetching} onClick={()=>{setSelected(p.id);setCreating(false)}}>Open repackaging {p.id}</Button></div>)}
  {!q.currentData.data.total&&<p>No repackaging findings retained.</p>}
  <div className="flex gap-3 items-center"><Button variant="outline" disabled={q.isFetching||page===1} onClick={()=>setPage(page-1)}>Previous repackaging records</Button><span>Page {page} / {q.currentData.data.last_page}</span><Button variant="outline" disabled={q.isFetching||page>=q.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next repackaging records</Button></div>
  {q.currentData.pending_output_change?<p>Resolve the pending repackaging, custody or yield correction before retaining another movement.</p>:user?.role==='pharmacist'&&<Button variant="outline" disabled={q.isFetching} onClick={()=>{setCreating(!creating);setSelected(null)}}>{creating?'Close repackaging form':'Record repackaging findings'}</Button>}
  {creating&&!q.currentData.pending_output_change&&<fieldset disabled={q.isFetching} className="min-w-0"><Context id={id} onDone={value=>{setCreating(false);setSelected(value);setPage(1)}}/></fieldset>}
  {selected&&<Detail key={selected} id={selected} batch={batch}/>}
 </section>
}
function Context({id,onDone}:{id:number;onDone:(id:number)=>void}){
 const q=useGetRepackagingContextQuery(id)
 if(q.isError)return <p role="alert">Repackaging source is unavailable. {errorMessage(q.error)} <Button onClick={()=>q.refetch()}>Retry repackaging source</Button></p>
 if(!q.currentData)return <p>Loading retained container balances…</p>
 return <fieldset disabled={q.isFetching} className="min-w-0"><Create context={q.currentData.data} onDone={onDone}/></fieldset>
}
function Observation({prefix,label,unit}:{prefix:string;label:string;unit:string}){return <fieldset className="border rounded p-3 space-y-3"><legend>{label}</legend><Field name={`${prefix}_quantity`} label={`${label}: final measured quantity (${unit})`}/><Evidence name={`${prefix}_evidence`} label={`${label}: final measurement evidence`}/></fieldset>}
function Create({context,onDone}:{context:RepackagingContext;onDone:(id:number)=>void}){
 const source=useRef(context).current;const request=useRef<{payload:string;id:string}|null>(null)
 const [newContainers,setNewContainers]=useState<{key:string;identifier:string;container_reference:string;storage_reference:string}[]>([])
 const [transfers,setTransfers]=useState<string[]>(['first']);const [save,{isLoading}]=useCreateRepackagingProposalMutation();const [error,setError]=useState('')
 const destinations=[...source.balance.containers.map(c=>c.identifier),...newContainers.map(c=>c.identifier).filter(Boolean)]
 const changed=context.source_hash!==source.source_hash
 function updateContainer(key:string,field:'identifier'|'container_reference'|'storage_reference',value:string){setNewContainers(rows=>rows.map(r=>r.key===key?{...r,[field]:value}:r))}
 async function submit(e:FormEvent<HTMLFormElement>){
  e.preventDefault();setError('');const f=new FormData(e.currentTarget);const v=(key:string)=>String(f.get(key)??'');const endpoint=(key:string)=>v(key)===UNPACKAGED?null:v(key)
  const observation=(prefix:string)=>({quantity:v(`${prefix}_quantity`),evidence:v(`${prefix}_evidence`)})
  const decision={unit:source.balance.unit,new_containers:newContainers.map(({identifier,container_reference,storage_reference})=>({identifier,container_reference,storage_reference})),
   transfers:transfers.map(key=>({from_identifier:endpoint(`${key}_from`),to_identifier:endpoint(`${key}_to`),quantity:v(`${key}_quantity`),evidence:v(`${key}_evidence`)})),
   observed_containers:[...source.balance.containers.map((c,i)=>({identifier:c.identifier,...observation(`old${i}`)})),...newContainers.map(c=>({identifier:c.identifier,...observation(c.key)}))],
   observed_unpackaged:observation('unpackaged'),process_evidence:v('process'),reconciliation_evidence:v('reconciliation')}
  const body={id:source.execution_id,source_hash:source.source_hash,decision};const payload=JSON.stringify(body)
  if(request.current?.payload!==payload)request.current={payload,id:crypto.randomUUID()}
  try{const r=await save({...body,request_id:request.current.id}).unwrap();onDone(r.data.id)}catch(e){setError(errorMessage(e))}
 }
 return <form onSubmit={submit} className="border rounded p-4 space-y-4"><h4 className="font-semibold">New repackaging findings</h4>
  <p>Held output: {source.balance.held_output} {source.balance.unit}. Prior disposal: {source.balance.previously_disposed}. Accounted yield: {source.balance.recorded_yield}.</p>
  <p className="text-sm">Use exact measured quantities with up to three decimal places. Every source must have enough material before this movement. Record sequential transfers separately. Final observations must reconcile for each container; use custody or quantity corrections for discrepancies.</p>
  {changed&&<p role="alert">Source evidence changed. Preserve your notes, then close and reopen this form.</p>}
  <fieldset disabled={isLoading||changed} className="space-y-4 min-w-0">
   <h5 className="font-semibold">New containers, if needed</h5><p className="text-sm">Identifiers are permanently reserved when you retain this proposal, including if it is later rejected.</p>
   {newContainers.map((c,i)=><fieldset key={c.key} className="border rounded p-3 space-y-3"><legend>New container {i+1}</legend><Field name={`${c.key}_identifier`} label={`New container ${i+1}: identifier`} maxLength={64} value={c.identifier} onChange={e=>updateContainer(c.key,'identifier',e.target.value)}/><Field name={`${c.key}_packaging`} label={`New container ${i+1}: packaging evidence`} maxLength={5000} value={c.container_reference} onChange={e=>updateContainer(c.key,'container_reference',e.target.value)}/><Field name={`${c.key}_storage`} label={`New container ${i+1}: storage evidence`} maxLength={5000} value={c.storage_reference} onChange={e=>updateContainer(c.key,'storage_reference',e.target.value)}/><Button type="button" variant="outline" onClick={()=>setNewContainers(rows=>rows.filter(r=>r.key!==c.key))}>Remove new container {i+1}</Button></fieldset>)}
   <Button type="button" variant="outline" disabled={newContainers.length+source.balance.containers.length>=100} onClick={()=>setNewContainers(rows=>[...rows,{key:crypto.randomUUID(),identifier:'',container_reference:'',storage_reference:''}])}>Add new container</Button>
   <h5 className="font-semibold">Measured movements</h5>
   {transfers.map((key,i)=><fieldset key={key} className="border rounded p-3 space-y-3"><legend>Movement {i+1}</legend><label className="grid gap-1 text-sm">Movement {i+1}: source<select required name={`${key}_from`} defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select source</option><option value={UNPACKAGED}>Unpackaged remainder ({source.balance.unpackaged_quantity})</option>{source.balance.containers.map(c=><option key={c.identifier} value={c.identifier}>{c.identifier} ({c.quantity})</option>)}</select></label><label className="grid gap-1 text-sm">Movement {i+1}: destination<select required name={`${key}_to`} defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select destination</option><option value={UNPACKAGED}>Unpackaged remainder</option>{destinations.map((id,n)=><option key={`${n}-${id}`} value={id}>{id}</option>)}</select></label><Field name={`${key}_quantity`} label={`Movement ${i+1}: quantity (${source.balance.unit})`}/><Evidence name={`${key}_evidence`} label={`Movement ${i+1}: measured movement evidence`}/><Button type="button" variant="outline" disabled={transfers.length===1} onClick={()=>setTransfers(rows=>rows.filter(k=>k!==key))}>Remove movement {i+1}</Button></fieldset>)}
   <Button type="button" variant="outline" disabled={transfers.length>=200} onClick={()=>setTransfers(rows=>[...rows,crypto.randomUUID()])}>Add movement</Button>
   <h5 className="font-semibold">Final observed contents</h5>
   {source.balance.containers.map((c,i)=><Observation key={c.identifier} prefix={`old${i}`} label={`Container ${c.identifier}`} unit={source.balance.unit}/>)}
   {newContainers.map((c,i)=><Observation key={`${c.key}-${c.identifier}`} prefix={c.key} label={`New container ${c.identifier||i+1}`} unit={source.balance.unit}/>)}
   <Observation prefix="unpackaged" label="Unpackaged remainder" unit={source.balance.unit}/>
   <Evidence name="process" label="Repackaging process evidence"/><Evidence name="reconciliation" label="Overall movement reconciliation evidence"/>
   <p className="text-sm">Saving retains findings for independent pharmacist review. It does not authorize medication use.</p>{error&&<p role="alert">{error}</p>}<Button>Retain repackaging findings</Button>
  </fieldset>
 </form>
}
function Quantity({label,value,unit}:{label:string;value:RepackagingQuantity;unit:string}){return <div className="border-b pb-2 space-y-1"><strong>{label}</strong><p>Before: {value.previous_quantity} · Out: {value.outgoing_quantity} · In: {value.incoming_quantity} · Final: {value.quantity} {unit}</p><p className="whitespace-pre-wrap break-words">{value.observation_evidence}</p></div>}
function Detail({id,batch}:{id:number;batch:CompoundBatch}){
 const {user}=useAuth();const q=useGetRepackagingProposalQuery(id);const [save,{isLoading}]=useDecideRepackagingProposalMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');setSuccess('');const f=new FormData(e.currentTarget);try{await save({id,decision:String(f.get('decision')) as 'applied'|'rejected',evidence:String(f.get('evidence'))}).unwrap();setSuccess('Repackaging decision retained. Output remains quarantined; no medication release authorized.')}catch(e){setError(errorMessage(e))}}
 if(q.isError)return <p role="alert">Could not load repackaging evidence. <Button onClick={()=>q.refetch()}>Retry repackaging detail</Button></p>
 if(!q.currentData)return <p>Loading repackaging evidence…</p>
 const p=q.currentData.data;const v=p.proposal;const independent=Number(user?.id)!==p.created_by&&Number(user?.id)!==batch.execution?.created_by&&!batch.execution?.addenda.some(a=>a.created_by===Number(user?.id))
 return <article className="border rounded p-4 space-y-3"><h4 className="font-semibold">Repackaging {p.id} · {p.status}</h4><p>Recorded by user {p.created_by} · {p.created_at}</p><p>Held output: {v.held_output} · Prior disposal: {v.previously_disposed} · Accounted yield: {v.recorded_yield} {v.unit}</p>
  <h5 className="font-semibold">Measured movements</h5>{v.transfers.map((t,i)=><div key={i} className="border-b pb-2"><p>{t.from_identifier??'Unpackaged remainder'} → {t.to_identifier??'Unpackaged remainder'}: {t.quantity} {v.unit}</p><p className="whitespace-pre-wrap break-words">{t.evidence}</p></div>)}
  <h5 className="font-semibold">Final contents and observations</h5>{v.containers.map(c=><div key={c.identifier} className="space-y-1"><Quantity label={`${c.identifier}${c.new_identity?' (new identifier)':''}`} value={c} unit={v.unit}/><p className="whitespace-pre-wrap break-words">Packaging: {c.container_reference}</p><p className="whitespace-pre-wrap break-words">Storage: {c.storage_reference}</p></div>)}<Quantity label="Unpackaged remainder" value={v.unpackaged} unit={v.unit}/>
  <p className="whitespace-pre-wrap break-words">Process: {v.process_evidence}</p><p className="whitespace-pre-wrap break-words">Reconciliation: {v.reconciliation_evidence}</p>
  {p.reviewed_by&&<><p>Reviewed by user {p.reviewed_by} · {p.reviewed_at}</p><p className="whitespace-pre-wrap break-words">{p.review_evidence}</p></>}
  {p.status==='pending'&&user?.role==='pharmacist'&&(independent?<form onSubmit={submit}><fieldset disabled={isLoading||q.isFetching} className="space-y-3"><label className="grid gap-1 text-sm">Repackaging decision<select name="decision" defaultValue="rejected" className="border rounded p-2 bg-background"><option value="rejected">Reject proposal; preserve findings and identifiers</option><option value="applied">Apply reconciled container movements</option></select></label><Evidence name="evidence" label="Independent repackaging review findings and evidence"/>{error&&<p role="alert">{error}</p>}<Button>Save repackaging decision</Button></fieldset></form>:<p>Another pharmacist independent of these findings and preparation must review this proposal.</p>)}{success&&<p role="status">{success}</p>}
 </article>
}
