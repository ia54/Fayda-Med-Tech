'use client'
import {useState} from 'react'
import {RecallNotice,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Field,errorMessage} from './fields'
import {Button} from '@/components/ui/button'
export function RecallCorrections({notice:n,role,userId,page,setPage}:{notice:RecallNotice;role:string;userId:number;page:number;setPage:(p:number)=>void}){
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [requestId]=useState(()=>crypto.randomUUID())
 async function submit(path:string,body:Record<string,unknown>){setError('');try{await save({path:`recall-notices/${n.id}/${path}`,body:{...body,request_id:requestId,version:n.version,confirmed:true}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <section className="border rounded p-4 space-y-4 min-w-0"><h2 className="text-xl font-semibold">Notice corrections</h2>
 <p className="text-sm">Use this only for an internal notice entered in error. Verify the source and register any correct replacement notice first. This process does not declare a real recall terminated or close patient follow-up.</p>
 {n.correction_pending&&<p role="status">A correction awaits independent review. The organization recall hold remains active.{page>1&&<Button variant="outline" onClick={()=>setPage(1)}>View latest request</Button>}</p>}
 {n.withdrawn_at&&<p role="status">Internal entry withdrawn {n.withdrawn_at}. Existing matching receipts were kept in quarantine or recalled status; each location must review its stock separately. Other recall and custody holds remain in force.</p>}
 {n.corrections.data.map(c=><div key={c.id} className="border-t pt-3 space-y-2 break-words"><h3 className="font-semibold">Correction #{c.id} · {c.status}</h3><p>{c.reason}</p><p>Source / correction evidence: {c.evidence}</p><p className="text-sm">Requested by pharmacist #{c.created_by} · {c.created_at}</p>
 {c.reviewed_at&&<><p>Independent evidence: {c.review_evidence}</p><p className="text-sm">Reviewed by pharmacist #{c.reviewed_by} · {c.reviewed_at}</p></>}
 {c.status==='pending'&&role==='pharmacist'&&(c.created_by===userId?<p>A different assigned pharmacist must review this request.</p>:<form className="grid gap-3" onSubmit={e=>{e.preventDefault();submit(`corrections/${c.id}/review`,Object.fromEntries(new FormData(e.currentTarget)))}}>
 <p className="text-sm">Applying stops this erroneous notice from holding future receipts. All currently matching receipts across the organization are quarantined; balances, existing recalls, reservations and follow-up are preserved. Rejecting keeps this notice active.</p>
 <label className="grid gap-1 text-sm">Independent correction decision<select required name="decision" defaultValue="" className="border rounded p-2 bg-background min-w-0 w-full"><option value="">Select a decision</option><option value="apply" disabled={n.version!==c.notice_version||Boolean(n.withdrawn_at)}>Withdraw erroneous internal entry</option><option value="reject">Reject correction; retain notice</option></select></label>
 <Field name="evidence" label="Independent source and scope verification" maxLength={5000}/><label className="flex items-start gap-2 text-sm"><input required type="checkbox"/>I independently verified the error, supporting source and any replacement notice. I understand this does not release existing stock or terminate a real recall.</label><Button className="h-auto whitespace-normal" disabled={isLoading}>Record independent correction decision</Button>
 </form>)}
 </div>)}
 {!n.corrections.data.length&&<p>No correction decisions recorded.</p>}
 <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isLoading} onClick={()=>setPage(page-1)}>Previous corrections</Button><span>Page {page} of {n.corrections.last_page}</span><Button variant="outline" disabled={page>=n.corrections.last_page||isLoading} onClick={()=>setPage(page+1)}>Next corrections</Button></div>
 {role==='pharmacist'&&!n.withdrawn_at&&!n.correction_pending&&<details><summary className="underline cursor-pointer">Request correction of an erroneous notice</summary><form className="grid gap-3 mt-3" onSubmit={e=>{e.preventDefault();submit('corrections',Object.fromEntries(new FormData(e.currentTarget)))}}>
 <Field name="reason" label="Error in the internal notice" maxLength={2000}/><Field name="evidence" label="Verified source and replacement-notice reference, if applicable" maxLength={5000}/><label className="flex items-start gap-2 text-sm"><input required type="checkbox"/>I verified this internal entry was recorded in error and retained the supporting source. Any valid replacement scope is already registered.</label><Button className="h-auto whitespace-normal" disabled={isLoading}>Request independent correction review</Button>
 </form></details>}
 {error&&<p role="alert">{error}</p>}
 </section>
}
