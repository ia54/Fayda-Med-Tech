"use client"
import {FormEvent,useState} from 'react'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {PharmacyPatient,useGetPharmacyLocationsQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function PatientLocations({patient,canEnroll}:{patient:PharmacyPatient;canEnroll:boolean}){
 const {currentData,isFetching,isError}=useGetPharmacyLocationsQuery(undefined,{skip:!canEnroll})
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('');const [message,setMessage]=useState('')
 const destinations=currentData?.data.filter(l=>l.active&&!patient.locations.some(p=>p.id===l.id))||[]
 async function submit(e:FormEvent<HTMLFormElement>){
  e.preventDefault();setError('');setMessage('')
  const fields=Object.fromEntries(new FormData(e.currentTarget))
  try{
   await save({path:`patients/${patient.id}/locations`,body:{...fields,version:patient.version,source_location_id:Number(fields.source_location_id),location_id:Number(fields.location_id),sharing_confirmed:fields.sharing_confirmed==='on'}}).unwrap()
   setMessage('Location enrollment retained. The shared patient chart is available to assigned pharmacy staff there. Prescriptions remain at their original locations; prior fill reviews require refresh.')
  }catch(e){setError(errorMessage(e))}
 }
 return <section className="space-y-3" aria-label="Patient pharmacy locations">
  <h2 className="text-xl font-semibold">Patient locations</h2>
  <p className="text-sm">Enrolled locations within your current assignments:</p>
  <ul className="list-disc pl-5">{patient.locations.map(l=><li key={l.id}>{l.name}</li>)}</ul>
  {canEnroll&&<details className="border rounded p-4 space-y-3">
   <summary className="cursor-pointer font-semibold">Enroll at another pharmacy location</summary>
   <p className="text-sm">This shares the same patient chart, clinical record and retained history with assigned pharmacy staff at the selected location. Verify the same person and the authority to share before proceeding. You must currently be assigned to both locations. No prescriptions, refills, stock or clinical approvals are transferred.</p>
   {isError?<p role="alert">Assigned locations could not be loaded. Refresh the patient chart before enrolling.</p>:!currentData?<p role="status">Loading assigned locations…</p>:destinations.length===0?<p>No additional assigned locations are available for enrollment.</p>:<form key={patient.version} onSubmit={submit} className="space-y-3">
    <fieldset disabled={isFetching||isLoading} className="space-y-3 min-w-0">
     <label className="grid gap-1 text-sm">Existing enrolled location<select name="source_location_id" required className="border rounded p-2 bg-background"><option value="">Select existing location</option>{patient.locations.map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>
     <label className="grid gap-1 text-sm">Additional pharmacy location<select name="location_id" required className="border rounded p-2 bg-background"><option value="">Select additional location</option>{destinations.map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>
     <Field name="reason" label="Reason for location enrollment" maxLength={2000}/>
     <Field name="identity_reference" label="Same-patient identity evidence" maxLength={2000}/>
     <Field name="sharing_authority_reference" label="Authority to share this patient chart" maxLength={2000}/>
     <label className="flex gap-2 text-sm"><input name="sharing_confirmed" type="checkbox" required/>I verified the same person and authority to share this chart and history with pharmacy staff at the selected location.</label>
     <Button>Enroll patient at additional location</Button>
    </fieldset>
   </form>}
  </details>}
  {error&&<p role="alert">{error}</p>}{message&&<p role="status">{message}</p>}
 </section>
}
