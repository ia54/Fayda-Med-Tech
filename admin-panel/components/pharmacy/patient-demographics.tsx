"use client"
import {FormEvent,useState} from 'react'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {PharmacyPatient,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function PatientDemographics({patient}:{patient:PharmacyPatient}){
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('');const [message,setMessage]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){
  e.preventDefault();setError('');setMessage('')
  const fields=Object.fromEntries(new FormData(e.currentTarget))
  try{
   await save({path:`patients/${patient.id}/demographics`,method:'PUT',body:{...fields,version:patient.version,same_patient_confirmed:fields.same_patient_confirmed==='on'}}).unwrap()
   setMessage('Patient details corrected and history retained. Existing fill reviews and labels must be checked again before preparation or handover.')
  }catch(e){setError(errorMessage(e))}
 }
 return <details className="border rounded p-4 space-y-3">
  <summary className="cursor-pointer font-semibold">Correct patient details</summary>
  <p className="text-sm">Correct details for this same person after checking identity evidence. This does not merge records, change the patient record number or move prescriptions to another person. The change applies to this shared patient chart at its existing locations. Previous values remain in history; prior fill reviews become stale.</p>
  <form key={patient.version} onSubmit={submit} className="space-y-3">
   <div className="grid gap-3 md:grid-cols-2">
    <Field name="first_name" label="Correct first name" defaultValue={patient.first_name} maxLength={100}/>
    <Field name="last_name" label="Correct last name" defaultValue={patient.last_name} maxLength={100}/>
    <Field name="date_of_birth" label="Correct date of birth" type="date" defaultValue={patient.date_of_birth}/>
    <Field name="phone" label="Correct phone" defaultValue={patient.phone||''} required={false} maxLength={50}/>
    <Field name="address" label="Correct address" defaultValue={patient.address||''} required={false} maxLength={255}/>
   </div>
   <Field name="reason" label="Reason for patient detail correction" maxLength={2000}/>
   <Field name="identity_reference" label="Identity evidence supporting this correction" maxLength={2000}/>
   <label className="flex gap-2 text-sm"><input name="same_patient_confirmed" type="checkbox" required/>I verified that these corrected details refer to the same person. This is not a patient merge or reassignment.</label>
   <Button disabled={isLoading}>Save patient detail correction</Button>
  </form>
  {error&&<p role="alert">{error}</p>}{message&&<p role="status">{message}</p>}
 </details>
}
