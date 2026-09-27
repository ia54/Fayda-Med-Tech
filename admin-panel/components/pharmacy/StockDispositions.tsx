'use client'
import {useState} from 'react'
import {type PharmacyLotDetail,type StockDisposition,useGetStockDispositionsQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'

export function StockDispositions({lot,userId,role,refreshing}:{lot:PharmacyLotDetail;userId:number;role:string;refreshing:boolean}){
 const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetStockDispositionsQuery({id:lot.id,page})
 const data=currentData?.data;const busy=refreshing||isFetching
 return <section className="border rounded p-4 space-y-4 min-w-0"><h2 className="font-semibold">Supplier return and disposal records</h2>
 <p className="text-sm">Synthetic preview. Record a completed external event with its source evidence. This form does not authorize transport or choose a disposal method. Only manufactured, noncontrolled, nonhazardous stock is supported here. Patient returns, controlled substances, compounded preparations, ingredients and unexplained custody losses require their separate procedures.</p>
 <p className="text-sm">A new record holds this receipt pending a different pharmacist’s review. The proposed quantity remains in the recorded balance until applied and must not be supplied. Applying the review deducts it once; rejecting leaves the receipt held for reconciliation. No recall is cleared, financial credit posted or stock automatically returned.</p>
 {isError?<p role="alert">Could not load disposition history. <Button variant="outline" onClick={()=>refetch()}>Retry dispositions</Button></p>:!data?<p role="status">Loading dispositions…</p>:<>
 {data.pending&&<p role="status" className="font-semibold">Disposition awaiting independent review. Resolve it before stock release, use or count adjustment.</p>}
 {role==='pharmacist'&&!data.pending&&!lot.count_pending&&lot.product&&Number(lot.reserved)===0&&Number(lot.on_hand)>0&&<DispositionForm key={`${lot.version}-${lot.product.id}`} lot={lot} disabled={busy}/>}
 {!lot.product&&<p className="text-sm">Verify the source product before recording a disposition.</p>}
 <ul className="space-y-3">{data.records.data.map(d=><li key={d.id} className="border rounded p-3 space-y-2 break-words"><h3 className="font-semibold">{d.kind.replaceAll('_',' ')} · {d.quantity} {lot.quantity_unit} · {d.status}</h3><p>{d.reason}</p><p>Reported event: {d.occurred_on} · Destination: {d.destination}</p><p>Recorded by pharmacist #{d.created_by} · {d.created_at}</p><details><summary className="underline cursor-pointer">Disposition evidence</summary><div className="space-y-2 mt-2 whitespace-pre-wrap"><p>Classification: {d.classification_evidence}</p><p>Authority / instructions: {d.authority_reference}</p><p>Completion / receipt: {d.completion_reference}</p></div></details>
 {d.reviewed_at&&<div><p>Reviewed by pharmacist #{d.reviewed_by} · {d.reviewed_at}</p><p className="whitespace-pre-wrap">{d.review_evidence}</p></div>}
 {d.status==='pending'&&(d.created_by===userId?<p className="text-sm">A different assigned pharmacist must review your record.</p>:role==='pharmacist'&&<DispositionReview key={`${d.id}-${lot.version}`} lot={lot} record={d} disabled={busy}/>)}
 </li>)}</ul>{!data.records.total&&<p>No disposition records for this receipt.</p>}
 <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||busy} onClick={()=>setPage(page-1)}>Previous dispositions</Button><span>Page {page} of {data.records.last_page} · {data.records.total} records</span><Button variant="outline" disabled={page>=data.records.last_page||busy} onClick={()=>setPage(page+1)}>Next dispositions</Button></div>
 </>}
 </section>
}
function DispositionForm({lot,disabled}:{lot:PharmacyLotDetail;disabled:boolean}){
 const [requestId]=useState(()=>crypto.randomUUID());const [error,setError]=useState('');const [mutate,{isLoading}]=usePharmacyStockActionMutation()
 return <details><summary className="underline cursor-pointer">Record completed stock disposition</summary><form className="grid gap-3 mt-3 md:grid-cols-2" onSubmit={async e=>{e.preventDefault();setError('');const body=Object.fromEntries(new FormData(e.currentTarget));try{await mutate({path:`stock/${lot.id}/dispositions`,body:{...body,request_id:requestId,version:lot.version,product_id:lot.product?.id,scope_confirmed:body.scope_confirmed==='on'}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <p className="md:col-span-2 text-sm">Verify the exact NDC, lot, quantity and unit against the physical event evidence. Product revision {lot.product?.revision}: {lot.product?.generic_name} · {lot.product?.strength} · {lot.product?.manufacturer}. Do not repeat a physical return or disposal because a record was rejected.</p>
 <label className="grid gap-1 text-sm">Disposition type<select required name="kind" defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select event type</option><option value="supplier_return">Supplier return</option><option value="disposal">Disposal</option></select></label>
 <Field name="quantity" label={`Disposition quantity (${lot.quantity_unit})`} type="number" min="0.001" max={lot.on_hand} step="0.001"/>
 <Field name="occurred_on" label="Completed event date" type="date"/>
 <Field name="destination" label="Receiving supplier / authorized disposal destination" maxLength={2000}/>
 <Evidence name="classification_evidence" label="Product and waste-classification evidence"/>
 <Evidence name="authority_reference" label="Return authorization / applicable disposal instructions"/>
 <Evidence name="completion_reference" label="Completed return receipt / disposal certificate and custody evidence"/>
 <Field name="reason" label="Disposition reason" maxLength={2000}/>
 <label className="flex gap-2 items-start text-sm md:col-span-2"><input name="scope_confirmed" type="checkbox" required/>I verified this synthetic event concerns manufactured, noncontrolled, nonhazardous stock from this receipt, with completed-event evidence. It is not a patient return, compounded product or unexplained loss.</label>
 {error&&<p role="alert" className="md:col-span-2 text-red-700 dark:text-red-300">{error}</p>}<Button disabled={disabled||isLoading}>Retain disposition for review</Button>
 </form></details>
}
function Evidence({name,label}:{name:string;label:string}){return <label className="grid gap-1 text-sm">{label}<textarea name={name} required maxLength={5000} className="border rounded p-2 bg-background"/></label>}
function DispositionReview({lot,record,disabled}:{lot:PharmacyLotDetail;record:StockDisposition;disabled:boolean}){
 const [requestId]=useState(()=>crypto.randomUUID());const [error,setError]=useState('');const [mutate,{isLoading}]=usePharmacyStockActionMutation();const stale=lot.version!==record.lot_version
 return <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');const body=Object.fromEntries(new FormData(e.currentTarget));try{await mutate({path:`stock/${lot.id}/dispositions/${record.id}/review`,body:{...body,request_id:requestId,confirmed:body.confirmed==='on'}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 {stale&&<p role="alert">Stock changed after this record. Reject it and reconcile the evidence before submitting a fresh record; do not repeat the physical event.</p>}
 <label className="grid gap-1 text-sm">Independent disposition decision<select name="decision" required defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select decision</option><option value="apply" disabled={stale}>Apply exact quantity deduction</option><option value="reject">Reject record and retain hold</option></select></label>
 <Evidence name="evidence" label="Independent source, quantity and completion verification"/>
 <label className="flex gap-2 items-start text-sm"><input name="confirmed" type="checkbox" required/>I independently reviewed the exact receipt, classification, quantity, destination, authority and completion evidence. Applying deducts {record.quantity} {lot.quantity_unit} once and preserves existing holds.</label>
 {error&&<p role="alert" className="text-red-700 dark:text-red-300">{error}</p>}<Button disabled={disabled||isLoading}>Save independent disposition review</Button>
 </form>
}
