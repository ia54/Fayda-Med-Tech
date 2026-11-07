"use client"
import {FormEvent,useState} from 'react'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {PharmacyPatient,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function PatientLocationWithdrawal({patient}:{patient:PharmacyPatient}){
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('');const [message,setMessage]=useState('')
 const eligible=patient.locations.filter(l=>l.enrollment_event_id!==null)
 async function submit(e:FormEvent<HTMLFormElement>){
  e.preventDefault();setError('');setMessage('')
  const f=Object.fromEntries(new FormData(e.currentTarget));const location=eligible.find(l=>l.id===Number(f.location_id))
  try{
   await save({path:`patients/${patient.id}/locations/withdraw`,body:{...f,version:patient.version,
    location_id:Number(f.location_id),retained_location_id:Number(f.retained_location_id),
    enrollment_event_id:location?.enrollment_event_id,withdrawal_confirmed:f.withdrawal_confirmed==='on'}}).unwrap()
   setMessage('Additional enrollment withdrawn and its history retained. Future access through that location is stopped. This cannot undo information already viewed or copied.')
  }catch(e){setError(errorMessage(e))}
 }
 return <div className="space-y-3">
  {eligible.length>0&&patient.locations.length>1&&<details className="border rounded p-4 space-y-3">
   <summary className="cursor-pointer font-semibold">Correct an erroneous location enrollment</summary>
   <p className="text-sm">Withdraw an additional enrollment entered in error before any prescription has been recorded at that location. The original chart and enrollment history remain. Select a different enrolled location where access must continue. You must be assigned to both sites. Previously viewed or copied information cannot be recalled by this action.</p>
   <form key={patient.version} onSubmit={submit} className="space-y-3">
    <fieldset disabled={isLoading} className="space-y-3 min-w-0">
     <label className="grid gap-1 text-sm">Incorrect additional location<select name="location_id" required className="border rounded p-2 bg-background"><option value="">Select incorrect enrollment</option>{eligible.map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>
     <label className="grid gap-1 text-sm">Retain chart access at<select name="retained_location_id" required className="border rounded p-2 bg-background"><option value="">Select retained location</option>{patient.locations.map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>
     <Field name="reason" label="Reason this enrollment was incorrect" maxLength={2000}/>
     <Field name="correction_reference" label="Enrollment correction evidence / incident reference" maxLength={2000}/>
     <label className="flex gap-2 text-sm"><input name="withdrawal_confirmed" type="checkbox" required/>I verified the incorrect enrollment and the location where this chart must remain available.</label>
     <Button>Withdraw incorrect enrollment</Button>
    </fieldset>
   </form>
  </details>}
  {error&&<p role="alert">{error}</p>}{message&&<p role="status">{message}</p>}
 </div>
}
