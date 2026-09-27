'use client'
import Link from 'next/link'
import {useState} from 'react'
import {PharmacyRx,useGetPharmacyQueueQuery,usePharmacyActionMutation} from '@/store/api/pharmacyApiSlice'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'

export function PrescriptionReplacement({rx,role}:{rx:PharmacyRx;role:string}){
 const eligible=!rx.replacement&&role==='pharmacist'
 const [search,setSearch]=useState('');const [page,setPage]=useState(1);const [selected,setSelected]=useState('')
 const {currentData,isFetching,isError,refetch}=useGetPharmacyQueueQuery({page,search,replacement_for:rx.id},{skip:!eligible})
 const [save,{isLoading}]=usePharmacyActionMutation();const [error,setError]=useState('');const [key]=useState(()=>crypto.randomUUID())
 if(!eligible&&!rx.replacement&&!rx.replaces)return null
 return <Card><CardHeader><CardTitle>Prescription continuity</CardTitle></CardHeader><CardContent className="space-y-4">
 {([['Original prescription',rx.replaces],['Replacement prescription',rx.replacement]] as const).map(([title,link])=>link&&<div key={title} className="border rounded p-3 space-y-2 break-words"><h3 className="font-semibold">{title}</h3><Link className="underline" href={`/dashboard/pharmacy/${link.id}`}>{link.rx_number} · {link.medication}</Link><p>{link.reason}</p><p>Authority / evidence: {link.reference}</p><p className="text-sm">Linked {link.created_at} · Pharmacist #{link.created_by}</p></div>)}
 {eligible&&<details><summary className="cursor-pointer underline">{rx.discontinued_at?'Link a separately received replacement':'Discontinue and replace prescription'}</summary><div className="space-y-4 mt-3"><p className="text-sm">Receive the new prescription with its own number, source evidence, quantities and refills first. Linking preserves both records and does not restore the original, copy an approval, grant refills or transfer stock. This link cannot be changed through this workflow.</p>{!rx.discontinued_at&&<p className="text-sm">Saving will discontinue this original and link the selected new prescription in one transaction. Future fills, preparation and handover on the original will stop. Resolve any open fills and ingredient reservations separately; this action does not return stock or notify anyone.</p>}<Link className="underline" href="/dashboard/pharmacy/new">Receive a new prescription</Link>
 <label className="grid gap-1 text-sm">Find replacement by prescription number or medication<input className="border rounded p-2 bg-background" value={search} onChange={e=>{setSearch(e.target.value);setPage(1);setSelected('')}}/></label>
 {isError?<p role="alert">Replacement candidates could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>:isFetching?<p role="status">Loading eligible prescriptions…</p>:<p className="text-sm">{currentData?.data.total||0} eligible records at this location for the same patient and accident case.</p>}
 <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');try{await save({id:rx.id,path:'replacement',body:{...Object.fromEntries(new FormData(e.currentTarget)),replacement_id:Number(selected),discontinue_original:!rx.discontinued_at,request_id:key}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <label className="grid gap-1 text-sm">Replacement prescription<select className="border rounded p-2 bg-background w-full" required value={selected} onChange={e=>setSelected(e.target.value)} disabled={isFetching||isError}><option value="">Select the received prescription</option>{currentData?.data.data.map(r=><option key={r.id} value={r.id}>{r.rx_number} · {r.medication}</option>)}</select></label>
 <div className="flex flex-wrap items-center gap-3"><Button type="button" variant="outline" disabled={page===1||isFetching} onClick={()=>{setPage(page-1);setSelected('')}}>Previous candidates</Button><span>Page {page} / {currentData?.data.last_page||1}</span><Button type="button" variant="outline" disabled={isFetching||page>=(currentData?.data.last_page||1)} onClick={()=>{setPage(page+1);setSelected('')}}>Next candidates</Button></div>
 <Field name="reason" label="Reason for replacement linkage" maxLength={2000}/><Field name="reference" label="Replacement authority / evidence reference" maxLength={2000}/><label className="flex gap-2 text-sm"><input type="checkbox" required/>{rx.discontinued_at?'I verified the original, replacement and supporting evidence.':'I verified the replacement authority and instruct discontinuation of this original. I will resolve its outstanding fills and reservations.'}</label>
 {error&&<p role="alert">{error}</p>}<Button className="h-auto whitespace-normal" disabled={isLoading||isFetching||isError||!selected}>{rx.discontinued_at?'Retain replacement link':'Discontinue original and retain replacement'}</Button>
 </form></div></details>}
 </CardContent></Card>
}
