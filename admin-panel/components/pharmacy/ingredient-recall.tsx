"use client"
import Link from 'next/link'
import {FormEvent,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {IngredientLot,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Field,errorMessage} from './fields'
export function IngredientRecall({lot}:{lot:IngredientLot}) {
 const {user}=useAuth();const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('')
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();setError('');try{await save({path:`ingredient-lots/${lot.id}/recall`,body:{...Object.fromEntries(new FormData(e.currentTarget)),version:lot.version}}).unwrap()}catch(e){setError(errorMessage(e))}}
 return <section className="border-t pt-4 space-y-3"><h3 className="font-semibold">Recall hold and batch trace</h3>
 <p className="text-sm">This hold applies to this receipt only. Check other receipts and locations against the original recall notice separately. It does not notify patients or suppliers, document disposal, or establish that a patient received a product.</p>
 {lot.recall_reference?<div role="status" className="border border-destructive rounded p-3 space-y-2 break-words"><strong>Recall hold — use prohibited</strong><p>{lot.recall_reference}</p><p className="whitespace-pre-wrap">{lot.recall_evidence}</p><p>User {lot.recalled_by} · {lot.recalled_at}</p><p className="text-sm">This hold cannot be cleared through stock review or physical-count adjustment. Disposition and recall closure require a separate workflow.</p></div>:user?.role==='pharmacist'&&<form onSubmit={submit} className="space-y-3"><Field name="reference" label="Recall notice reference" maxLength={2000}/><Field name="evidence" label="Recall scope and matching evidence" maxLength={5000}/><label className="flex gap-2 text-sm"><input type="checkbox" required/>I checked this receipt against the notice. This blocks its use and retains the recall evidence.</label>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading} className="h-auto whitespace-normal">Record recall hold on this receipt</Button></form>}
 <h4 className="font-medium">Linked worksheet history</h4>
 {!lot.trace.length?<p>No ingredient allocations link this receipt to a worksheet.</p>:lot.trace.map((t,i)=><div key={`${t.batch_id}-${t.ingredient_key}-${i}`} className="border rounded p-3 break-words"><Link className="underline" href={`/dashboard/pharmacy/compounding?view=batch&id=${t.batch_id}`}>{t.batch_number}</Link><p>{t.ingredient_key} · {t.quantity} {lot.quantity_unit} · {t.status}</p><p className="text-sm">{t.status==='consumed'?'Ingredient recorded as consumed: investigate the preparation record.':t.status==='reserved'?'Reserved for this worksheet; preparation must not proceed while recalled.':'Reservation released; this entry does not establish consumption.'}</p></div>)}
 </section>
}
