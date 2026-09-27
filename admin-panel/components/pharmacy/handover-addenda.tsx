"use client"

import {FormEvent,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {useGetHandoverAddendaQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Field,errorMessage} from './fields'

export function HandoverAddenda({rxId,fillId}:{rxId:number;fillId:number}) {
 const {user}=useAuth()
 const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetHandoverAddendaQuery({rxId,fillId,page})
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [draft,setDraft]=useState<{request_id:string;ledger_token:string}|null>(null)
 const [error,setError]=useState('')
 const data=currentData?.data
 const path=`prescriptions/${rxId}/fills/${fillId}/handover-addenda`
 async function submit(event:FormEvent<HTMLFormElement>,reviewId?:number) {
  event.preventDefault();setError('')
  const form=event.currentTarget
  const entries=Object.fromEntries(new FormData(form))
  try {
   await save({path:reviewId?`${path}/${reviewId}/review`:path,body:{...entries,...(reviewId?{}:draft),confirmed:entries.confirmed==='on'}}).unwrap()
   if(!reviewId){setDraft(null);setPage(1)}
  } catch(e){setError(errorMessage(e))}
 }
 return <section className="rounded border p-3 space-y-3 min-w-0 break-words">
  <h4 className="font-semibold">Handover corrections and additional evidence</h4>
  <p className="text-sm">The original handover above remains unchanged. Proposed addenda require review by a different assigned pharmacist. Read accepted addenda alongside the original record; pending and rejected statements are not accepted corrections. These records do not change quantities, inventory, labels, completion status or payments.</p>
  {isError&&<p role="alert">Corrections could not be loaded. <button type="button" className="underline" onClick={()=>refetch()}>Retry</button></p>}
  {!data&&!isError&&<p role="status">Loading retained corrections…</p>}
  {data&&<>
   {data.addenda.total===0&&<p className="text-sm">No addenda recorded.</p>}
   {data.addenda.data.map(a=><article key={a.id} className="border rounded p-3 space-y-2 text-sm">
    <h5 className="font-semibold">Addendum #{a.id} · {a.section.replaceAll('_',' ')} · {a.status}</h5>
    <p>Requested by pharmacist #{a.created_by} · {a.created_at}</p>
    <dl className="space-y-2">{[['Proposed correction / additional statement',a.statement],['Reason',a.reason],['Evidence reference',a.evidence]].map(([label,value])=><div key={label}><dt className="font-medium">{label}</dt><dd className="whitespace-pre-wrap">{value}</dd></div>)}</dl>
    {a.reviewed_by&&<p className="whitespace-pre-wrap">Reviewed by pharmacist #{a.reviewed_by} · {a.reviewed_at}<br/>{a.review_evidence}</p>}
    {a.status==='pending'&&user?.role==='pharmacist'&&(Number(user.id)===a.created_by?<p>A different assigned pharmacist must review your request.</p>:<form onSubmit={e=>submit(e,a.id)} className="grid gap-3 min-w-0">
     <label className="grid gap-1 min-w-0">Review decision<select name="decision" required defaultValue="" className="h-10 w-full min-w-0 border rounded px-3 bg-background"><option value="">Select after reviewing</option><option value="accepted">Accept documentary addendum</option><option value="rejected">Reject documentary addendum</option></select></label>
     <Field name="evidence" label="Independent review findings and evidence reference" maxLength={5000}/>
     <label className="flex items-start gap-2"><input name="confirmed" type="checkbox" required className="mt-1"/>I reviewed the original handover, proposed statement and supporting evidence.</label>
     <Button className="h-auto min-h-10 w-full whitespace-normal py-2" disabled={isLoading||isFetching||isError}>Retain review decision</Button>
    </form>)}
   </article>)}
   {data.addenda.last_page>1&&<nav aria-label="Handover addendum pages" className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous addenda</Button><span>Page {page} of {data.addenda.last_page}</span><Button variant="outline" disabled={page>=data.addenda.last_page||isFetching} onClick={()=>setPage(page+1)}>Next addenda</Button></nav>}
   {user?.role==='pharmacist'&&<>
    <Button variant="outline" disabled={isLoading||isFetching||isError} onClick={()=>{setError('');setDraft(draft?null:{request_id:crypto.randomUUID(),ledger_token:data.ledger_token})}}>{draft?'Close correction form':'Add correction or evidence'}</Button>
    {draft&&<form onSubmit={e=>submit(e)} className="grid gap-3 min-w-0">
     <label className="grid gap-1 text-sm min-w-0">Record section<select name="section" required defaultValue="" className="h-10 w-full min-w-0 border rounded px-3 bg-background"><option value="">Select section</option>{['recipient','identity_checks','representative_authority','counseling','delivery','completion_reference'].map(s=><option key={s} value={s}>{s.replaceAll('_',' ')}</option>)}</select></label>
     <label className="grid gap-1 text-sm min-w-0">Correction or additional statement<textarea name="statement" required maxLength={5000} rows={4} className="border rounded p-2 bg-background"/></label>
     <Field name="reason" label="Reason for correction or additional evidence" maxLength={2000}/>
     <Field name="evidence" label="Supporting evidence reference" maxLength={5000}/>
     <p className="text-sm">Identify the original entry and the correction explicitly. Do not enter identity-document numbers. Quantity, medication, missed delivery or patient-safety discrepancies require separate investigation; an addendum cannot resolve them.</p>
     <label className="flex items-start gap-2 text-sm"><input name="confirmed" type="checkbox" required className="mt-1"/>I have checked this documentary statement and its evidence. The original record will remain visible.</label>
     <Button className="h-auto min-h-10 w-full whitespace-normal py-2" disabled={isLoading||isFetching||isError}>Request independent review</Button>
    </form>}
   </>}
  </>}
  {error&&<p role="alert" className="text-sm">{error}</p>}
 </section>
}
