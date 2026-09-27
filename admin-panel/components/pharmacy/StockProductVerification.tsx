"use client"
import {useState} from 'react'
import {type PharmacyLotDetail,type StockProduct,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Field,errorMessage} from './fields'
import {Button} from '@/components/ui/button'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'

export function ProductDetails({product:p}:{product:StockProduct}){
 return <div className="space-y-1 break-words text-sm"><p className="font-semibold">Revision {p.revision} · {p.brand_name?`${p.brand_name} (${p.generic_name})`:p.generic_name}</p><p>{p.strength} · {p.dosage_form} · Manufacturer / supplier: {p.manufacturer}</p><p>Verified {p.verified_on} · Pharmacist #{p.actor_id}</p><p className="whitespace-pre-wrap">Source: {p.evidence}</p><p className="whitespace-pre-wrap">Reason: {p.reason}</p><p>Recorded {p.created_at}</p></div>
}

export function StockProductVerification({lot,role,page,changePage,refreshing}:{lot:PharmacyLotDetail;role:string;page:number;changePage:(page:number)=>void;refreshing:boolean}){
 const p=lot.product
 return <Card><CardHeader><CardTitle>Verified product information</CardTitle></CardHeader><CardContent className="space-y-4 min-w-0">
 <p className="text-sm">Check the physical product and its source documentation against this receipt’s NDC, lot and expiry. These retained details support product selection; they do not establish substitution eligibility or generate a dispensing label.</p>
 {p?<ProductDetails product={p}/>:<p role="status">No pharmacist verification recorded. Fill approval, preparation and handover require verified product information.</p>}
 {role==='pharmacist'&&<ProductForm key={`${lot.id}-${p?.revision||0}`} lot={lot} refreshing={refreshing}/>}
 <details><summary className="cursor-pointer underline">Product verification history ({lot.product_history.total})</summary><div className="space-y-3 mt-3">{lot.product_history.data.map(record=><div key={record.id} className="border rounded p-3"><ProductDetails product={record}/></div>)}<div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||refreshing} onClick={()=>changePage(page-1)}>Previous product records</Button><span>Page {page} of {lot.product_history.last_page}</span><Button variant="outline" disabled={page>=lot.product_history.last_page||refreshing} onClick={()=>changePage(page+1)}>Next product records</Button></div></div></details>
 </CardContent></Card>
}
function ProductForm({lot,refreshing}:{lot:PharmacyLotDetail;refreshing:boolean}){
 const [requestId]=useState(()=>crypto.randomUUID());const [error,setError]=useState('');const [mutate,{isLoading}]=usePharmacyStockActionMutation();const p=lot.product
 return <details><summary className="cursor-pointer underline">{p?'Record corrected product verification':'Verify receipt product'}</summary><form className="grid gap-3 mt-3 md:grid-cols-2" onSubmit={async e=>{e.preventDefault();setError('');const body=Object.fromEntries(new FormData(e.currentTarget));try{await mutate({path:`stock/${lot.id}/product`,body:{...body,request_id:requestId,revision:p?.revision||0,confirmed:body.confirmed==='on'}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <p className="text-sm md:col-span-2">A new revision retains the previous details. Pending fills need a new pharmacist review; prepared fills must be cancelled and started again. Stock quantities, holds and completed handovers stay unchanged.</p>
 {error&&<p role="alert" className="md:col-span-2 text-red-700 dark:text-red-300">{error}</p>}
 <Field name="generic_name" label="Generic / established drug name" defaultValue={p?.generic_name} maxLength={255}/>
 <Field name="brand_name" label="Brand name (leave blank if unbranded)" required={false} defaultValue={p?.brand_name||''} maxLength={255}/>
 <Field name="strength" label="Verified product strength" defaultValue={p?.strength} maxLength={255}/>
 <Field name="dosage_form" label="Verified dosage form" defaultValue={p?.dosage_form} maxLength={255}/>
 <Field name="manufacturer" label="Manufacturer / supplier shown on source product" defaultValue={p?.manufacturer} maxLength={255}/>
 <Field name="verified_on" label="Product verification date" type="date"/>
 <label className="grid gap-1 text-sm md:col-span-2">Product source evidence and receipt match<textarea name="evidence" required maxLength={5000} className="border rounded p-2 bg-background"/></label>
 <label className="grid gap-1 text-sm md:col-span-2">Reason for verification or correction<textarea name="reason" required maxLength={2000} className="border rounded p-2 bg-background"/></label>
 <label className="flex gap-2 items-start text-sm md:col-span-2"><input name="confirmed" type="checkbox" required/>I checked the product details, brand status, NDC, lot and expiry against the source product and this receipt.</label>
 <Button disabled={isLoading||refreshing}>{isLoading?'Saving verification…':'Save product verification'}</Button>
 </form></details>
}
