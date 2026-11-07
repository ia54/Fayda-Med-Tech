"use client"
import {PatientDemographics} from '@/components/pharmacy/patient-demographics'
import {PatientLocations} from '@/components/pharmacy/patient-locations'
import {PatientHistory} from '@/components/pharmacy/patient-history'
import Link from 'next/link'
import {useParams} from 'next/navigation'
import {useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {useGetPharmacyPatientQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Field,Choice,errorMessage} from '@/components/pharmacy/fields'
import {Button} from '@/components/ui/button'
export default function PharmacyPatient(){
 const id=Number(useParams().id);const {user}=useAuth();const allowed=['pharmacist','pharmacy_technician'].includes(user?.role||'');const {currentData,isFetching,isError}=useGetPharmacyPatientQuery(id,{skip:!allowed||!Number.isInteger(id)});const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [message,setMessage]=useState('');const p=currentData?.data
 if(!allowed)return <p>Clinical records are restricted to pharmacy staff.</p>
 if(isError)return <p role="alert">Patient record unavailable. Check your location assignment.</p>
 if(!p)return <p role="status">Loading patient record…</p>
 return <fieldset disabled={isFetching} aria-busy={isFetching} className="min-w-0 max-w-4xl space-y-5"><Link href="/dashboard/pharmacy/patients" className="underline">Back to patients</Link><h1 className="text-3xl font-bold text-primary">{p.first_name} {p.last_name}</h1><p>{p.record_number} · DOB {p.date_of_birth} · {p.phone||'Phone not recorded'}</p><p>{p.address||'Address not recorded'}</p>{user?.role==='pharmacist'&&<PatientDemographics key={p.id} patient={p}/>}<PatientLocations key={`locations-${p.id}`} patient={p} canEnroll={user?.role==='pharmacist'}/><h2 className="text-xl font-semibold">Clinical record</h2><p>Unknown is different from none reported. This record supports pharmacist review; no automated interaction or dosing check is connected.</p><dl className="space-y-2">{Object.entries(p.clinical).map(([k,v])=><div key={k}><dt className="font-semibold">{k.replaceAll('_',' ')}</dt><dd className="whitespace-pre-wrap">{v?(k.endsWith('_status')?v.replaceAll('_',' '):v):'Not recorded'}</dd></div>)}</dl>
 {user?.role==='pharmacist'&&<details><summary className="cursor-pointer font-semibold">Record clinical review</summary><form key={p.version} className="grid gap-4 mt-4 md:grid-cols-2" onSubmit={async e=>{e.preventDefault();setError('');setMessage('');const f=Object.fromEntries(new FormData(e.currentTarget));try{await save({path:`patients/${id}/clinical`,method:'PUT',body:{...f,version:p.version}}).unwrap();setMessage('Clinical review saved. Existing fill approvals must match this record version.')}catch(e){setError(errorMessage(e))}}}>
 {['allergies','medications'].map(k=><div className="space-y-3" key={k}><Choice name={`${k}_status`} label={`${k} status`} options={['unknown','none_reported','documented']} defaultValue={p.clinical[`${k}_status`]||'unknown'}/><label className="grid gap-1">{k} details<textarea name={k} defaultValue={p.clinical[k]||''} className="border rounded p-2 bg-background"/></label></div>)}<label className="grid gap-1">Relevant clinical history<textarea name="history" defaultValue={p.clinical.history||''} className="border rounded p-2 bg-background"/></label><Field name="reviewed_on" label="Review date" type="date"/><Field name="source_reference" label="Information source / evidence"/><Button disabled={isLoading}>Save pharmacist review</Button></form></details>}{error&&<p role="alert">{error}</p>}{message&&<p role="status">{message}</p>}<PatientHistory key={`history-${p.id}`} id={p.id}/></fieldset>
}
