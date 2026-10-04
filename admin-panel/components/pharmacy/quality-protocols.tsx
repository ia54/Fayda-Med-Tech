"use client"
import {FormEvent,useRef,useState} from 'react'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {useAuth} from '@/hooks/useAuth'
import {CompoundFormula,useGetPharmacyLocationsQuery,useGetQualityProtocolsQuery,useGetQualityProtocolQuery,useGetQualityProtocolHistoryQuery,useCreateQualityProtocolMutation,useDecideQualityProtocolMutation} from '@/store/api/pharmacyApiSlice'

function Evidence({name,label}:{name:string;label:string}) {
 return <label className="grid gap-1 text-sm">{label}<textarea name={name} required maxLength={5000} rows={3} className="rounded border p-2 bg-background"/></label>
}
export function QualityProtocols({formula}:{formula:CompoundFormula}) {
 const [location,setLocation]=useState('')
 const locations=useGetPharmacyLocationsQuery()
 return <section className="border-t pt-4 space-y-3"><h3 className="font-semibold">Location quality protocols</h3>
  <p className="text-sm">Retain the checks and methods supplied by the responsible pharmacist. Protocol review is documentary governance; it does not verify laboratory results, assign a beyond-use date or release medication.</p>
  {locations.isError?<p role="alert">Could not load locations. <Button onClick={()=>locations.refetch()}>Retry protocol locations</Button></p>:!locations.currentData?<p>Loading protocol locations…</p>:<label className="grid gap-1 text-sm">Quality protocol location<select value={location} onChange={e=>setLocation(e.target.value)} className="rounded border p-2 bg-background" disabled={locations.isFetching}><option value="">Select an assigned location</option>{locations.currentData.data.filter(l=>l.active).map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>}
  {location&&!locations.isError&&locations.currentData?.data.some(l=>l.active&&l.id===Number(location))&&<ProtocolList key={`${formula.id}:${location}`} formula={formula} location={Number(location)}/>}
 </section>
}
function ProtocolList({formula,location}:{formula:CompoundFormula;location:number}) {
 const {user}=useAuth();const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null);const [creating,setCreating]=useState(false)
 const q=useGetQualityProtocolsQuery({page,location_id:location,formulation_id:formula.id})
 const latest=useGetQualityProtocolsQuery({page:1,location_id:location,formulation_id:formula.id})
 const current=latest.currentData?.data.data[0]
 const busy=q.isFetching||latest.isFetching
 if(q.isError||latest.isError)return <p role="alert">Could not load protocols. <Button onClick={()=>{q.refetch();latest.refetch()}}>Retry quality protocols</Button></p>
 if(!q.currentData||!latest.currentData)return <p>Loading quality protocols…</p>
 return <div className="space-y-4">
  <p>{q.currentData.data.total} retained protocol revisions</p>
  {q.currentData.data.data.map(p=><div key={p.id} className="flex flex-wrap justify-between gap-2 border-b pb-2"><p>Protocol {p.id} · Revision {p.revision_number} · {p.status}</p><Button variant="outline" disabled={busy} onClick={()=>{setSelected(p.id);setCreating(false)}}>Open quality protocol {p.id}</Button></div>)}
  <div className="flex flex-wrap items-center gap-3"><Button variant="outline" disabled={busy||page===1} onClick={()=>setPage(page-1)}>Previous protocols</Button><span>Page {page} / {q.currentData.data.last_page}</span><Button variant="outline" disabled={busy||page>=q.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next protocols</Button></div>
  {user?.role==='pharmacist'&&formula.status==='reviewed'&&(current?.status==='draft'?<p>Review or reject the latest draft before creating a replacement.</p>:<Button variant="outline" disabled={busy} onClick={()=>{setCreating(!creating);setSelected(null)}}>{creating?'Close protocol form':'Create quality protocol revision'}</Button>)}
  {creating&&<fieldset disabled={busy} aria-busy={busy} className="min-w-0"><ProtocolCreate location={location} formula={formula.id} previous={current?.id??null} onDone={id=>{setCreating(false);setSelected(id);setPage(1)}}/></fieldset>}
  {selected&&<ProtocolDetail key={selected} id={selected}/>}
 </div>
}
function ProtocolCreate({location,formula,previous,onDone}:{location:number;formula:number;previous:number|null;onDone:(id:number)=>void}) {
 const [rows,setRows]=useState([1]);const next=useRef(2);const request=useRef<{body:string;id:string}|null>(null)
 const [save,{isLoading}]=useCreateQualityProtocolMutation();const [error,setError]=useState('')
 // Freeze lineage when the form opens. A concurrent revision must cause a conflict, never silent rebasing.
 const previousAtOpen=useRef(previous)
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');const d=new FormData(e.currentTarget);const value=(name:string)=>String(d.get(name)??'')
  const body={location_id:location,formulation_id:formula,previous_id:previousAtOpen.current,evidence:value('evidence'),record:{reference:value('reference'),revision:value('revision'),requirements:rows.map(id=>({key:value(`key_${id}`),label:value(`label_${id}`),criterion:value(`criterion_${id}`),method_reference:value(`method_${id}`)}))}}
  const serialized=JSON.stringify(body);if(request.current?.body!==serialized)request.current={body:serialized,id:crypto.randomUUID()}
  try {const r=await save({...body,request_id:request.current.id}).unwrap();onDone(r.data.id)} catch(e){setError(errorMessage(e))}
 }
 return <form onSubmit={submit} className="space-y-3 rounded border p-3"><h4 className="font-semibold">New protocol revision</h4><p className="text-sm">Define every required check. No clinical criteria are generated. A second assigned pharmacist must review the retained draft.</p>
  <fieldset disabled={isLoading} className="space-y-3 min-w-0"><Field name="reference" label="Quality protocol source reference" maxLength={5000}/><Field name="revision" label="Source protocol revision" maxLength={5000}/>
  {rows.map((id,i)=><fieldset key={id} className="border rounded p-3 space-y-2"><legend>Requirement {i+1}</legend><Field name={`key_${id}`} label={`Requirement ${i+1} identifier`} maxLength={40}/><Field name={`label_${id}`} label={`Requirement ${i+1} name`} maxLength={5000}/><Evidence name={`criterion_${id}`} label={`Requirement ${i+1} acceptance criterion`}/><Evidence name={`method_${id}`} label={`Requirement ${i+1} method reference`}/>{rows.length>1&&<Button type="button" variant="outline" onClick={()=>setRows(rows.filter(r=>r!==id))}>Remove requirement {i+1}</Button>}</fieldset>)}
  <Button type="button" variant="outline" disabled={rows.length>=100} onClick={()=>setRows([...rows,next.current++])}>Add quality requirement</Button><Evidence name="evidence" label="Protocol rationale and supporting evidence"/>
  {error&&<p role="alert">{error}</p>}<Button type="submit">Save quality protocol draft</Button></fieldset>
 </form>
}
function ProtocolDetail({id}:{id:number}) {
 const {user}=useAuth();const q=useGetQualityProtocolQuery(id);const [page,setPage]=useState(1);const history=useGetQualityProtocolHistoryQuery({id,page})
 const [save,{isLoading}]=useDecideQualityProtocolMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('')
 async function decide(e:FormEvent<HTMLFormElement>){e.preventDefault();if(!q.currentData)return;setError('');setSuccess('');const f=new FormData(e.currentTarget)
  try {await save({id,version:q.currentData.data.version,decision:String(f.get('decision')) as 'reviewed'|'rejected'|'retired',evidence:String(f.get('evidence'))}).unwrap();setSuccess('Protocol decision retained. No medication release authorized.')}catch(e){setError(errorMessage(e))}}
 if(q.isError)return <p role="alert">Could not load protocol evidence. <Button onClick={()=>q.refetch()}>Retry quality protocol detail</Button></p>
 if(!q.currentData)return <p>Loading protocol detail…</p>
 const p=q.currentData.data;const independent=Number(user?.id)!==p.created_by
 return <article className="rounded border p-4 space-y-3"><h4 className="font-semibold">Quality protocol {p.id} · Revision {p.revision_number} · {p.status}</h4><p className="break-words">{p.record.reference} · Source revision {p.record.revision}</p>
  {!p.formulation_evidence_current&&<p role="alert">The formulation evidence has changed. This protocol cannot receive a new review against the old evidence.</p>}
  {p.record.requirements.map(r=><dl key={r.key} className="border-b pb-3"><dt className="font-medium">{r.key} · {r.label}</dt><dd className="whitespace-pre-wrap break-words">Criterion: {r.criterion}</dd><dd className="whitespace-pre-wrap break-words">Method: {r.method_reference}</dd></dl>)}
  {user?.role==='pharmacist'&&((p.status==='draft'&&independent)||p.status==='reviewed')&&<form key={`${p.id}:${p.version}`} onSubmit={decide}><fieldset disabled={q.isFetching||isLoading} className="space-y-3"><label className="grid gap-1 text-sm">Quality protocol decision<select name="decision" defaultValue={p.status==='reviewed'?'retired':'rejected'} className="border rounded p-2 bg-background">{p.status==='draft'?<><option value="rejected">Reject draft</option>{p.formulation_evidence_current&&<option value="reviewed">Record independent protocol review</option>}</>:<option value="retired">Retire protocol</option>}</select></label><Evidence name="evidence" label="Quality protocol review evidence"/>{error&&<p role="alert">{error}</p>}<Button>Save quality protocol decision</Button></fieldset></form>}
  {p.status==='draft'&&!independent&&<p>Another assigned pharmacist must review or reject your draft.</p>}{success&&<p role="status">{success}</p>}
  <h5 className="font-semibold">Protocol decision history</h5>{history.isError?<p role="alert">Could not load history. <Button onClick={()=>history.refetch()}>Retry protocol history</Button></p>:!history.currentData?<p>Loading protocol history…</p>:<>{history.currentData.data.data.map(h=><div key={h.id} className="text-sm border-b py-2"><p>{h.action} · User {h.actor_id} · {h.created_at}</p><p className="whitespace-pre-wrap break-words">{h.evidence}</p></div>)}<div className="flex gap-3 items-center"><Button variant="outline" disabled={history.isFetching||page===1} onClick={()=>setPage(page-1)}>Previous protocol events</Button><span>Page {page} / {history.currentData.data.last_page}</span><Button variant="outline" disabled={history.isFetching||page>=history.currentData.data.last_page} onClick={()=>setPage(page+1)}>Next protocol events</Button></div></>}
 </article>
}
