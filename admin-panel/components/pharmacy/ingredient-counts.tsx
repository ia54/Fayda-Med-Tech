"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {IngredientLot,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Choice,Field,errorMessage} from './fields'

export function IngredientCounts({lot}:{lot:IngredientLot}) {
 const {user}=useAuth();const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('');const request=useRef('')
 const pending=lot.counts.some(c=>c.status==='pending')
 async function submit(event:FormEvent<HTMLFormElement>,countId?:number) {
  event.preventDefault();setError('');const form=event.currentTarget
  const body:Record<string,unknown>=Object.fromEntries(new FormData(event.currentTarget))
  if(!countId){if(!request.current)request.current=crypto.randomUUID();body.request_id=request.current;body.version=lot.version}
  try{await save({path:countId?`ingredient-lots/${lot.id}/counts/${countId}/review`:`ingredient-lots/${lot.id}/counts`,body}).unwrap();request.current='';form.reset()}catch(e){setError(errorMessage(e))}
 }
 return <section className="border-t pt-4 space-y-4"><h3 className="font-semibold">Physical count and discrepancy review</h3>
  <p className="text-sm">Record the physical quantity remaining in this receipt, including reserved stock. A discrepancy immediately quarantines the lot. A different pharmacist must review it before the recorded balance changes. Include physically present damaged stock; quarantine and disposal are separate. This does not document disposal, satisfy controlled-substance reporting, or correct previous batch measurements.</p>
  {error&&<p role="alert">{error}</p>}
  {!pending?<form onSubmit={e=>submit(e)} className="space-y-3">
   <Field name="counted_quantity" label={`Physical quantity remaining (${lot.quantity_unit})`} type="number" min="0" max="999999.999" step="0.001"/>
   <Choice name="reason" label="Discrepancy category" options={['physical_count','observed_loss']}/>
   <Field name="evidence" label="Count evidence, findings and source reference" maxLength={5000}/>
   <Button disabled={isLoading} className="whitespace-normal h-auto">Record discrepancy and quarantine lot</Button>
  </form>:<p className="font-medium">A discrepancy awaits independent review. Reservation and preparation are blocked while this lot is quarantined.</p>}
  {lot.counts.map(c=><article key={c.id} className="border rounded p-3 space-y-3 break-words">
   <h4 className="font-medium">Count {c.id} · {c.status} · {c.reason.replaceAll('_',' ')}</h4>
   <p>Recorded {c.recorded_quantity} → counted {c.counted_quantity} {lot.quantity_unit}</p>
   <p className="text-sm">User {c.created_by} · {c.created_at} · Stock version {c.lot_version}</p>
   <p className="whitespace-pre-wrap text-sm">{c.evidence}</p>
   {c.reviewed_by&&<p className="text-sm whitespace-pre-wrap">Reviewer {c.reviewed_by} · {c.reviewed_at}: {c.review_evidence}</p>}
   {c.status==='pending'&&user?.role==='pharmacist'&&(Number(user.id)===c.created_by?<p>A different pharmacist must review this count.</p>:<form onSubmit={e=>submit(e,c.id)} className="space-y-3">
    <Choice name="decision" label={`Count ${c.id} review decision`} options={['reject','apply']}/>
    <Field name="evidence" label={`Count ${c.id} independent review evidence`} maxLength={5000}/>
    <p className="text-sm">Applying replaces the recorded on-hand balance with this count. It cannot reduce stock below reservations. Stock changes after the count require rejection and recount. The lot remains quarantined after either decision.</p>
    <Button disabled={isLoading} className="whitespace-normal h-auto">Save count review</Button>
   </form>)}
  </article>)}
 </section>
}
