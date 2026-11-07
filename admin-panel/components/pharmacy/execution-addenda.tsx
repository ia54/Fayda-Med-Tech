"use client"
import {FormEvent, useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {CompoundBatch, usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Choice, Field, errorMessage} from './fields'

export function ExecutionAddenda({batch}:{batch:CompoundBatch}) {
 const {user}=useAuth()
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('')
 const [open,setOpen]=useState(false)
 const [requestId,setRequestId]=useState('')
 const execution=batch.execution
 if(!execution) return null
 async function submit(event:FormEvent<HTMLFormElement>) {
  event.preventDefault()
  const form=event.currentTarget
  setError('')
  try {
   await save({path:`batch-worksheets/${batch.id}/execution/addenda`,body:{...Object.fromEntries(new FormData(form)),version:execution!.version,request_id:requestId}}).unwrap()
   form.reset();setOpen(false);setRequestId('')
  } catch(error) {setError(errorMessage(error))}
 }
 return <section className="space-y-3 border-t pt-4">
  <h4 className="font-semibold">Corrections and addenda</h4>
  <p className="text-sm">The original record is retained. Addenda document corrections or additional evidence; they do not adjust inventory or release output. A reviewed record requires fresh independent review after an addendum. Rejected records remain rejected.</p>
  {execution.addenda.length===0?<p className="text-sm">No addenda recorded.</p>:execution.addenda.map(a=><article key={a.id} className="border rounded p-3 space-y-2 break-words">
   <h5 className="font-medium">Addendum {a.id} · {a.section.replaceAll('_',' ')}</h5>
   <p className="text-sm">User {a.created_by} · {a.created_at} · Execution version {a.execution_version}</p>
   <dl className="text-sm space-y-2">{[['Correction / additional statement',a.statement],['Reason',a.reason],['Evidence reference',a.evidence]].map(([label,value])=><div key={label}><dt className="font-medium">{label}</dt><dd className="whitespace-pre-wrap">{value}</dd></div>)}</dl>
  </article>)}
  {user?.role==='pharmacist'&&<><Button variant="outline" className="hover:text-foreground" onClick={()=>{setOpen(!open);setError('');if(!open)setRequestId(crypto.randomUUID())}}>{open?'Close addendum form':'Add correction or evidence'}</Button>
  {open&&<form onSubmit={submit} className="space-y-3">
   <Choice name="section" label="Record section" options={['personnel','equipment','process','quality_results','measurements','yield','deviations','environment','hazard_controls','other']}/>
   <label className="grid gap-1 text-sm">Correction or additional statement<textarea name="statement" required maxLength={5000} rows={4} className="border rounded p-2 bg-background"/></label>
   <Field name="reason" label="Reason for addendum" maxLength={2000}/><Field name="evidence" label="Supporting evidence reference" maxLength={5000}/>
   <p className="text-sm">A different pharmacist who contributed neither the original execution nor an addendum must review the amended record. Quantity differences still require a separate inventory reconciliation workflow.</p>
   {error&&<p role="alert">{error}</p>}<Button disabled={isLoading}>Save retained addendum</Button>
  </form>}</>}
 </section>
}
