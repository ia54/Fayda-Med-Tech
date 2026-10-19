"use client"
import {useState} from 'react'
import {Button} from '@/components/ui/button'
import {useGetPharmacyPatientHistoryQuery} from '@/store/api/pharmacyApiSlice'

const demographics=['first_name','last_name','date_of_birth','phone','address']
const clinical=['allergies_status','allergies','medications_status','medications','history','reviewed_on','source_reference']
const record=(value:unknown):Record<string,unknown>=>value&&typeof value==='object'&&!Array.isArray(value)?value as Record<string,unknown>:{}
const display=(key:string,value:unknown)=>value===null||value===undefined||value===''?'Not recorded':key.endsWith('_status')?String(value).replaceAll('_',' '):String(value)

export function PatientHistory({id}:{id:number}){
 const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetPharmacyPatientHistoryQuery({id,page})
 const data=currentData?.data
 return <section className="space-y-3" aria-label="Patient record history">
  <h2 className="text-xl font-semibold">Retained history</h2>
  {isError?<p role="alert">Could not load patient history. <Button variant="outline" onClick={()=>refetch()}>Retry patient history</Button></p>:isFetching?<p role="status">Loading patient history…</p>:<>
   {!data?.total&&<p>No patient history entries.</p>}
   <ul className="space-y-3">{data?.data.map(event=>{
    const d=event.details;const action=String(d.action||'recorded')
    const fields=action==='demographics_corrected'?demographics:action==='clinical_review'?clinical:[]
    const before=record(d.previous),after=record(d.recorded)
    return <li key={event.id} className="border rounded p-3 break-words">
     <p className="text-sm">{event.created_at} · Staff #{event.actor_id}</p>
     <details><summary className="cursor-pointer font-semibold">{action.replaceAll('_',' ')}</summary>
      {fields.length>0?<div className="space-y-3 pt-3">
       {Boolean(d.reason)&&<p>{String(d.reason)}</p>}
       {Boolean(d.identity_reference)&&<p>Identity evidence: {String(d.identity_reference)}</p>}
       <dl className="space-y-3">{fields.map(key=><div key={key}><dt className="font-medium capitalize">{key.replaceAll('_',' ')}</dt><dd className="text-sm whitespace-pre-wrap">Before: {display(key,before[key])}</dd><dd className="text-sm whitespace-pre-wrap">After: {display(key,after[key])}</dd></div>)}</dl>
       <p className="text-sm">Patient record revision {String(d.version)}</p>
      </div>:action==='location_enrollment_withdrawn'?<div className="space-y-2 pt-3"><p>Withdrew additional enrollment #{String(d.enrollment_event_id)} at location #{String(d.location_id)}. Chart access retained at location #{String(d.retained_location_id)}.</p><p>Reason: {String(d.reason)}</p><p>Correction evidence: {String(d.correction_reference)}</p><p>Patient record revision {String(d.version)}</p></div>:action==='location_enrolled'?<div className="space-y-2 pt-3"><p>Enrolled at location #{String(d.location_id)} from existing location #{String(d.source_location_id)}.</p><p>Reason: {String(d.reason)}</p><p>Identity evidence: {String(d.identity_reference)}</p><p>Sharing authority: {String(d.sharing_authority_reference)}</p><p>Patient record revision {String(d.version)}</p></div>:action==='created'?<p className="pt-3">Identity evidence: {String(d.identity_reference||'Not recorded')} · Original location #{String(d.location_id||'Not recorded')}</p>:<pre className="whitespace-pre-wrap text-sm">{JSON.stringify(d,null,2)}</pre>}
     </details>
    </li>
   })}</ul>
  </>}
  <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous patient history</Button><span>Page {page} of {data?.last_page||1}</span><Button variant="outline" disabled={isFetching||isError||!data||page>=data.last_page} onClick={()=>setPage(page+1)}>Next patient history</Button></div>
 </section>
}
