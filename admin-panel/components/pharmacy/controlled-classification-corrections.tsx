"use client"
import {useState} from 'react'
import {useGetClassificationCorrectionsQuery,useGetPrescriptionSourcesQuery,usePharmacyActionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Field,errorMessage} from './fields'

const names={controlled_schedule:'Recorded schedule',controlled_source_format:'Received format',controlled_classification_reference:'Evidence reference'}
const display=(value:string|null)=>!value||value==='unknown'?'Unresolved':value

export function ControlledClassificationCorrections({rxId,role}:{rxId:number;role:string}){
 const [page,setPage]=useState(1),[requestId,setRequestId]=useState(()=>crypto.randomUUID()),[error,setError]=useState(''),[success,setSuccess]=useState('')
 const {currentData,isFetching,isError,refetch}=useGetClassificationCorrectionsQuery({id:rxId,page})
 const {currentData:sources,isFetching:loadingSources,isError:sourceError}=useGetPrescriptionSourcesQuery(rxId)
 const [save,{isLoading}]=usePharmacyActionMutation();const data=currentData?.data
 return <Card><CardHeader><CardTitle>Controlled classification history</CardTitle></CardHeader><CardContent className="space-y-4 min-w-0">
 <p className="text-sm">A pharmacist can resolve or correct the recorded schedule, receipt format and evidence reference. Original intake and every correction remain retained. This does not change the order, authenticate a prescriber, certify EPCS or enable controlled dispensing.</p>
 {isError?<p role="alert">Classification history could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>:!data?<p role="status">Loading classification history…</p>:<>
 <p>Retained corrections: {data.corrections.total}</p>
 {data.corrections.data.map(c=><details key={c.id} className="rounded border p-3 min-w-0 break-words"><summary className="cursor-pointer">Correction {c.revision} · {c.created_at} · Pharmacist #{c.created_by}</summary><div className="space-y-3 mt-3"><p>{c.reason}</p><p>Retained source document #{c.source_document_id}</p><dl className="space-y-3">{Object.entries(names).map(([key,name])=><div key={key}><dt className="font-semibold">{name}</dt><dd>Before: {display(c.before_snapshot[key as keyof typeof names])}</dd><dd>After: {display(c.after_snapshot[key as keyof typeof names])}</dd></div>)}</dl><details><summary>Source integrity reference</summary><code className="break-all">{c.source_sha256}</code></details></div></details>)}
 {!data.corrections.total&&<p>No corrections recorded. The current values are the retained intake classification.</p>}
 <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous corrections</Button><span>Page {page} of {data.corrections.last_page}</span><Button variant="outline" disabled={page>=data.corrections.last_page||isFetching} onClick={()=>setPage(page+1)}>Next corrections</Button></div>
 {role==='pharmacist'&&<details><summary className="cursor-pointer underline">Resolve or correct classification</summary><form key={`${data.revision}-${data.source_token}`} className="grid gap-3 mt-3 min-w-0" onSubmit={async e=>{e.preventDefault();setError('');setSuccess('');const input=Object.fromEntries(new FormData(e.currentTarget));try{await save({id:rxId,path:'classification-corrections',body:{request_id:requestId,source_token:data.source_token,values:{controlled_schedule:input.controlled_schedule,controlled_source_format:input.controlled_source_format,controlled_classification_reference:input.controlled_classification_reference},source_document_id:Number(input.source_document_id),reason:input.reason,confirmed:true}}).unwrap();setRequestId(crypto.randomUUID());setPage(1);setSuccess('Classification correction retained. Controlled dispensing remains disabled.')}catch(e){setError(errorMessage(e))}}}>
 <p className="text-sm">First attach supporting material under Original prescription evidence. Compare it with the current record. Choose unresolved if the evidence does not establish the classification.</p>
 <label className="grid gap-1 text-sm">Corrected recorded schedule<select name="controlled_schedule" required defaultValue={data.current.controlled_schedule||'unknown'} className="border rounded p-2 bg-background w-full min-w-0">{['unknown','II','III','IV','V'].map(v=><option key={v} value={v}>{v==='unknown'?'Unresolved':`Schedule ${v}`}</option>)}</select></label>
 <label className="grid gap-1 text-sm">Corrected received format<select name="controlled_source_format" required defaultValue={data.current.controlled_source_format||'unknown'} className="border rounded p-2 bg-background w-full min-w-0">{[['unknown','Unresolved'],['paper','Paper'],['electronic','Electronic (not EPCS verification)'],['fax','Fax'],['oral','Oral communication']].map(([v,name])=><option key={v} value={v}>{name}</option>)}</select></label>
 <Field name="controlled_classification_reference" label="Corrected classification evidence reference or unresolved issue" maxLength={2000} defaultValue={data.current.controlled_classification_reference||''}/>
 <label className="grid gap-1 text-sm">Retained classification evidence<select name="source_document_id" required defaultValue="" disabled={loadingSources||sourceError} className="border rounded p-2 bg-background w-full min-w-0"><option value="">Select attached evidence</option>{sources?.data.map(s=><option key={s.id} value={s.id}>#{s.id} · {s.original_name} · {s.reference}</option>)}</select></label>
 {sourceError&&<p role="alert">Source evidence could not be loaded. Refresh before saving.</p>}
 <Field name="reason" label="Reason for classification correction" maxLength={2000}/>
 <label className="flex gap-2 items-start text-sm"><input type="checkbox" required/>I compared the retained evidence and current classification. This correction does not change the prescription order or grant dispensing authority.</label>
 <Button className="h-auto whitespace-normal" disabled={isLoading||isFetching||loadingSources||sourceError||!sources?.data.length}>Retain classification correction</Button>
 </form></details>}
 </>}{error&&<p role="alert">{error}</p>}{success&&<p role="status">{success}</p>}
 </CardContent></Card>
}
