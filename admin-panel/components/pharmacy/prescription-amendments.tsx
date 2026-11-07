"use client"
import {useState} from 'react'
import {PharmacyRx,useGetPrescriptionAmendmentsQuery,useGetPrescriptionSourcesQuery,usePharmacyActionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Field,errorMessage} from './fields'
const names:Record<string,string>={strength:'Strength',dosage_form:'Dosage form',directions:'Directions',quantity:'Quantity per allowance',refills_authorized:'Authorized refills'}
export function PrescriptionAmendments({rx,role}:{rx:PharmacyRx;role:string}){
 const [page,setPage]=useState(1),[key,setKey]=useState(()=>crypto.randomUUID()),[error,setError]=useState(''),[success,setSuccess]=useState('');
 const {currentData,isFetching,isError,refetch}=useGetPrescriptionAmendmentsQuery({id:rx.id,page});
 const {currentData:sources,isFetching:loadingSources,isError:sourceError}=useGetPrescriptionSourcesQuery(rx.id);
 const [save,{isLoading}]=usePharmacyActionMutation();const data=currentData?.data;
 return <Card><CardHeader><CardTitle>Prescription amendments</CardTitle></CardHeader><CardContent className="space-y-4 min-w-0">
 <p className="text-sm">Retain a prescriber-authorized correction before the first supply. Original values and evidence remain in history. Patient, medication identity, prescriber, dates and quantity unit cannot be changed here. A new order or a change after supply uses a separately received replacement. Controlled and compounded amendments remain unavailable.</p>
 {isError?<p role="alert">Amendment history could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>:!data?<p role="status">Loading amendments…</p>:<>
 <p>Current prescription revision {data.revision}</p>
 {data.amendments.data.map(a=><details key={a.id} className="rounded border p-3 min-w-0 break-words"><summary className="cursor-pointer">Revision {a.revision} · {a.created_at} · Pharmacist #{a.created_by}</summary><div className="space-y-3 mt-3"><p>{a.reason}</p><p>Consultation on {a.consulted_on}: {a.consultation_evidence}</p><p>Retained source document #{a.source_document_id}</p><dl className="space-y-3">{Object.entries(names).map(([k,name])=><div key={k}><dt className="font-semibold">{name}</dt><dd>Before: {String(a.before_snapshot[k])}</dd><dd>After: {String(a.after_snapshot[k])}</dd></div>)}</dl><details><summary>Source integrity reference</summary><code className="break-all">{a.source_sha256}</code></details></div></details>)}
 {!data.amendments.total&&<p>No amendments have been recorded. The received prescription is revision 1.</p>}
 <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous amendments</Button><span>Page {page} of {data.amendments.last_page}</span><Button variant="outline" disabled={page>=data.amendments.last_page||isFetching} onClick={()=>setPage(page+1)}>Next amendments</Button></div>
 {data.hold_reason?<p className="text-sm" role="status">{data.hold_reason}</p>:role==='pharmacist'&&<details><summary className="cursor-pointer underline">Record prescriber-authorized amendment</summary><form key={`${data.revision}-${data.source_token}`} className="grid gap-3 mt-3 min-w-0" onSubmit={async e=>{e.preventDefault();setError('');setSuccess('');const input=Object.fromEntries(new FormData(e.currentTarget));const values=Object.fromEntries(Object.keys(names).map(k=>[k,input[k]]));try{await save({id:rx.id,path:'amendments',body:{request_id:key,source_token:data.source_token,values,source_document_id:Number(input.source_document_id),consulted_on:input.consulted_on,consultation_evidence:input.consultation_evidence,reason:input.reason,confirmed:true}}).unwrap();setKey(crypto.randomUUID());setPage(1);setSuccess('Amendment retained. Create a new fill and complete a fresh pharmacist review before preparation.')}catch(e){setError(errorMessage(e))}}}>
 <p className="text-sm">First attach the consultation or corrected-order evidence under Original prescription evidence. Verify the prescriber and exact change. Cancel any open fill before saving; this form never releases stock or carries over an approval.</p>
 <Field name="strength" label="Amended strength" defaultValue={rx.strength} maxLength={100}/><Field name="dosage_form" label="Amended dosage form" defaultValue={rx.dosage_form} maxLength={100}/>
 <label className="grid gap-1 text-sm min-w-0">Amended directions<textarea name="directions" required maxLength={2000} defaultValue={rx.directions} className="border rounded p-2 bg-background w-full min-w-0"/></label>
 <Field name="quantity" label={`Amended quantity per allowance (${rx.quantity_unit})`} type="number" min="0.001" max="999999.999" step="0.001" defaultValue={rx.quantity}/><Field name="refills_authorized" label="Amended authorized refills" type="number" min="0" max="99" step="1" defaultValue={rx.refills_authorized}/>
 <label className="grid gap-1 text-sm min-w-0">Retained amendment evidence<select name="source_document_id" required defaultValue="" className="border rounded p-2 bg-background w-full min-w-0" disabled={loadingSources||sourceError}><option value="">Select attached evidence</option>{sources?.data.map(s=><option key={s.id} value={s.id}>#{s.id} · {s.original_name} · {s.reference}</option>)}</select></label>
 {sourceError&&<p role="alert">Source evidence could not be loaded. Refresh before saving.</p>}
 <Field name="consulted_on" label="Prescriber consultation date" type="date" min={rx.written_on}/><Field name="consultation_evidence" label="Prescriber identity, consultation method and authorization evidence" maxLength={5000}/><Field name="reason" label="Reason for amendment" maxLength={2000}/>
 <label className="flex items-start gap-2 text-sm"><input type="checkbox" required/>I personally verified the prescriber consultation, attached evidence and amended values. No prior supply is being rewritten and a fresh pharmacist review is required.</label>
 <Button className="h-auto whitespace-normal" disabled={isLoading||isFetching||loadingSources||sourceError||!sources?.data.length}>Retain prescription amendment</Button>
 </form></details>}
 </>}{error&&<p role="alert">{error}</p>}{success&&<p role="status">{success}</p>}
 </CardContent></Card>
}
