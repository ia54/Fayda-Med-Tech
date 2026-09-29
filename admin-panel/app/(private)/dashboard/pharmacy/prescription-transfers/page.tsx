'use client'
import Link from 'next/link'
import {useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {useGetRxTransfersQuery,useGetPharmacyLocationsQuery} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
export default function PrescriptionTransfers(){
 const {user}=useAuth();const allowed=['pharmacist','pharmacy_technician'].includes(user?.role||'')
 const [page,setPage]=useState(1),[status,setStatus]=useState('pending')
 const {currentData,isFetching,isError,refetch}=useGetRxTransfersQuery({page,status:status||undefined},{skip:!allowed})
 const {currentData:sites}=useGetPharmacyLocationsQuery(undefined,{skip:!allowed})
 const name=(id:number)=>sites?.data.find(s=>s.id===id)?.name||`Location #${id}`
 if(!allowed)return <p>Pharmacy staff access is required.</p>
 return <div className="space-y-5 min-w-0"><Link className="underline" href="/dashboard/pharmacy">Back to pharmacy</Link><h1 className="text-3xl font-bold text-primary">Prescription transfers</h1>
 <p>Synthetic preview. Requests hold further supply at the sending location. A different receiving pharmacist must review the retained original evidence and remaining quantities. Stock transfers are handled separately.</p>
 <label className="grid gap-1 text-sm">Prescription transfer status<select className="border rounded p-2 bg-background" value={status} onChange={e=>{setStatus(e.target.value);setPage(1)}}><option value="">All statuses</option>{['pending','accepted','rejected','cancelled'].map(s=><option key={s} value={s}>{s.charAt(0).toUpperCase()+s.slice(1)}</option>)}</select></label>
 {isError?<p role="alert">Transfers could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>:isFetching?<p role="status">Loading transfer worklist…</p>:<div className="space-y-3">{currentData?.data.data.map(t=><div key={t.id} className="border rounded p-4 space-y-2 break-words"><Link className="underline font-semibold" href={`/dashboard/pharmacy/prescription-transfers/${t.id}`}>Prescription transfer #{t.id}</Link><p>{name(t.source_location_id)} → {name(t.destination_location_id)}</p><p>{t.status} · Sent {t.created_at} · Pharmacist #{t.sent_by}</p></div>)}{!currentData?.data.data.length&&<p>No prescription transfers match this view.</p>}</div>}
 <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={isFetching||page===1} onClick={()=>setPage(page-1)}>Previous transfers</Button><span>Page {page} of {currentData?.data.last_page||1}</span><Button variant="outline" disabled={isFetching||page>=(currentData?.data.last_page||1)} onClick={()=>setPage(page+1)}>Next transfers</Button></div>
 </div>
}
