'use client'
import {useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {ExtractionChoice,ExtractionDetail,useGetExtractionsQuery,useGetExtractionQuery,useReviewExtractionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {errorMessage} from './fields'

type EditorChoice=Omit<ExtractionChoice,'action'>&{action:ExtractionChoice['action']|''}
const label=(name:string)=>name.replaceAll('_',' ')
export function ExtractionDrafts({id}:{id:number}){
 const [open,setOpen]=useState(false)
 return <section className="border-t pt-4 space-y-3"><h3 className="font-semibold">Extraction draft review</h3><p className="text-sm">AI is not connected. Retained suggestions require comparison with the original file and a pharmacist decision. A draft review cannot change this prescription or authorize dispensing.</p><Button variant="outline" onClick={()=>setOpen(!open)}>{open?'Close extraction history':'View extraction history'}</Button>{open&&<History id={id}/>}</section>
}
function History({id}:{id:number}){
 const [page,setPage]=useState(1),[selected,setSelected]=useState<number|null>(null)
 const {currentData,isFetching,isError,refetch}=useGetExtractionsQuery({id,page})
 if(isError)return <p role="alert">Could not load extraction history. <Button onClick={()=>refetch()}>Retry</Button></p>
 if(!currentData)return <p role="status">Loading extraction history…</p>
 return <div className="space-y-3">{currentData.data.total===0&&<p>No extraction attempts retained. No provider request is available.</p>}{currentData.data.data.map(a=><div key={a.id} className="border rounded p-3 space-y-2 break-words"><p>Attempt #{a.id} · {a.status} · {a.model}</p><p className="text-sm">{a.created_at} · Staff #{a.requested_by} · Transcription #{a.transcription_id}</p>{a.failure_code&&<p>Outcome: {label(a.failure_code)}</p>}<Button variant="outline" onClick={()=>setSelected(selected===a.id?null:a.id)}>{selected===a.id?'Close attempt':'Inspect retained attempt'}</Button>{selected===a.id&&<Detail id={a.id}/>}</div>)}<div className="flex flex-wrap items-center gap-2"><Button variant="outline" disabled={isFetching||page===1} onClick={()=>{setPage(page-1);setSelected(null)}}>Previous attempts</Button><span>Page {page} of {currentData.data.last_page}</span><Button variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>{setPage(page+1);setSelected(null)}}>Next attempts</Button></div></div>
}
function Detail({id}:{id:number}){
 const {user}=useAuth();const {currentData,isFetching,isError,refetch}=useGetExtractionQuery(id)
 if(isError)return <p role="alert">Could not verify this retained attempt. <Button onClick={()=>refetch()}>Retry attempt</Button></p>
 if(!currentData)return <p role="status">Loading retained evidence…</p>
 const a=currentData.data
 return <div className="space-y-3"><p>Provider reference: {a.provider_reference}</p><p>Original source #{a.source_document_id}. Retrieve its file from Original prescription evidence above before reviewing.</p><details><summary className="cursor-pointer underline">Retained transcription used for this attempt</summary><p>{a.source_transcription.method} · {a.source_transcription.reference}</p>{a.source_transcription.pages.map((p,n)=><div key={n}><strong>Page {n+1}</strong><p className="whitespace-pre-wrap break-words">{p||'No readable text recorded.'}</p></div>)}</details>
 {a.draft&&Object.entries(a.draft.fields).map(([name,f])=><div key={name} className="border-l pl-3"><h4 className="capitalize font-medium">{label(name)} · {label(f.status)}</h4><p className="text-sm">Current order: {a.current_order_values[name]??'Compare patient identity in the patient record'}</p>{f.candidates.length===0?<p>No candidate supplied.</p>:f.candidates.map((c,n)=><div key={n} className="text-sm my-2"><p>Candidate {n+1}: {c.value}</p><p>Page {c.page}</p><blockquote className="whitespace-pre-wrap break-words">{c.quote}</blockquote></div>)}</div>)}
 {a.review?<div role="status" className="border rounded p-3 space-y-2"><strong>{a.review.decision==='dismiss'?'Draft dismissed':'Field decisions retained'}</strong><p>Pharmacist #{a.review.reviewed_by} · {a.review.created_at}</p><p>{a.review.evidence}</p>{Object.entries(a.review.fields).map(([name,c])=><p key={name} className="break-words"><span className="capitalize">{label(name)}</span>: {c.action}{c.value!==null?` — ${c.value}`:''}. {c.reason}</p>)}<p>The prescription remains unchanged.</p></div>:a.status==='completed'&&a.draft&&user?.role==='pharmacist'?<Review key={`${a.id}-${a.draft_sha256}`} attempt={a} disabled={isFetching}/>:<p>{a.status==='pending'?'No completed result is available.':a.status==='failed'?'No usable draft was retained.':'Only an assigned pharmacist can retain a review.'}</p>}
 </div>
}
function Review({attempt:a,disabled}:{attempt:ExtractionDetail;disabled:boolean}){
 const [decision,setDecision]=useState<'review'|'dismiss'>('review'),[choices,setChoices]=useState<Record<string,EditorChoice>>({}),[evidence,setEvidence]=useState(''),[confirmed,setConfirmed]=useState(false),[error,setError]=useState(''),[requestId]=useState(()=>crypto.randomUUID())
 const [save,{isLoading}]=useReviewExtractionMutation();const busy=disabled||isLoading
 const change=(name:string,patch:Partial<EditorChoice>)=>{setChoices(old=>({...old,[name]:{...(old[name]??{action:'',candidate_index:null,value:null,reason:''}),...patch}}));setConfirmed(false)}
 return <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');try{await save({id:a.id,body:{request_id:requestId,draft_sha256:a.draft_sha256,decision,fields:decision==='dismiss'?[]:choices,evidence,confirmed}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <label className="grid gap-1">Decision<select className="border rounded p-2 bg-background" disabled={busy} value={decision} onChange={e=>{setDecision(e.target.value as 'review'|'dismiss');setConfirmed(false)}}><option value="review">Review each field</option><option value="dismiss">Dismiss entire draft</option></select></label>
 {decision==='review'&&Object.entries(a.draft!.fields).map(([name,f])=><fieldset key={name} disabled={busy} className="border rounded p-3 grid gap-2 min-w-0"><legend className="capitalize">{label(name)}</legend><label className="grid gap-1 text-sm">Field decision<select className="border rounded p-2 bg-background w-full" required value={choices[name]?.action??''} onChange={e=>change(name,{action:e.target.value as ExtractionChoice['action'],candidate_index:null,value:null})}><option value="" disabled>Choose a decision</option>{f.candidates.length>0&&<option value="accept">Accept a source candidate</option>}<option value="reject">Reject / leave unpopulated</option><option value="correct">Record a manual correction</option></select></label>
 {choices[name]?.action==='accept'&&<label className="grid gap-1 text-sm">Source candidate<select className="border rounded p-2 bg-background w-full" required value={choices[name].candidate_index??''} onChange={e=>change(name,{candidate_index:Number(e.target.value)})}><option value="" disabled>Select candidate</option>{f.candidates.map((c,n)=><option key={n} value={n}>Candidate {n+1} · Page {c.page}: {c.value}</option>)}</select></label>}
 {choices[name]?.action==='correct'&&<label className="grid gap-1 text-sm">Manually verified value<input className="border rounded p-2 bg-background" required maxLength={2000} value={choices[name].value??''} onChange={e=>change(name,{value:e.target.value})}/></label>}
 <label className="grid gap-1 text-sm">Reason and original evidence reference<textarea className="border rounded p-2 bg-background" required maxLength={2000} value={choices[name]?.reason??''} onChange={e=>change(name,{reason:e.target.value})}/></label></fieldset>)}
 <label className="grid gap-1 text-sm">Overall comparison evidence / dismissal reason<textarea className="border rounded p-2 bg-background" required maxLength={5000} disabled={busy} value={evidence} onChange={e=>{setEvidence(e.target.value);setConfirmed(false)}}/></label><label className="flex gap-2 items-start text-sm"><input type="checkbox" required checked={confirmed} disabled={busy} onChange={e=>setConfirmed(e.target.checked)}/>I compared the original evidence and patient/order context, or am dismissing this draft. These retained decisions do not amend the prescription or approve dispensing.</label><Button disabled={busy} className="h-auto whitespace-normal">{decision==='dismiss'?'Retain dismissal':'Retain field decisions'}</Button>{error&&<p role="alert">{error}</p>}
 </form>
}
