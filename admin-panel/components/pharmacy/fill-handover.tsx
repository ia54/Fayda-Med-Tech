"use client"

import {useState} from 'react'
import {Field} from '@/components/pharmacy/fields'

export type HandoverEvidence = {
 recipient_type:'patient'|'representative';recipient_name:string;identity_reference:string;
 relationship?:string|null;authority_reference?:string|null;counseling_reference:string;
 delivery_method?:'pharmacy_staff'|'tracked_carrier';delivery_reference?:string;
 recorded_by:number;recorded_at:string;
}

export function handoverInput(entries:Record<string,FormDataEntryValue>) {
 return Object.fromEntries(Object.entries(entries).filter(([key])=>key.startsWith('handover_')).map(([key,value])=>[key.slice(9),key==='handover_confirmed'?value==='on':value]))
}

export function HandoverFields({delivery}:{delivery:boolean}) {
 const [recipient,setRecipient]=useState('')
 return <fieldset className="md:col-span-2 grid gap-3 rounded border p-3 min-w-0">
  <legend className="font-semibold px-1">Recipient and handover checks</legend>
  <p className="text-sm">Record a completed handover to the patient or an authorized representative. A dispatch, unattended package or carrier tracking update alone does not confirm receipt. Use evidence references; do not enter identity-document numbers or copies.</p>
  <label className="grid gap-1 text-sm">Recipient type<select required name="handover_recipient_type" value={recipient} onChange={e=>setRecipient(e.target.value)} className="border rounded h-10 px-3 bg-background"><option value="">Select who received it</option><option value="patient">Patient</option><option value="representative">Authorized representative</option></select></label>
  <Field name="handover_recipient_name" label="Recipient name" maxLength={255}/>
  <Field name="handover_identity_reference" label="Identity checks performed / evidence reference" maxLength={2000}/>
  {recipient==='representative'&&<><Field name="handover_relationship" label="Relationship to patient" maxLength={255}/><Field name="handover_authority_reference" label="Authority to receive for the patient / verification reference" maxLength={2000}/></>}
  {delivery&&<><label className="grid gap-1 text-sm">Delivery method<select required name="handover_delivery_method" defaultValue="" className="border rounded h-10 px-3 bg-background"><option value="">Select delivery method</option><option value="pharmacy_staff">Pharmacy staff</option><option value="tracked_carrier">Tracked carrier</option></select></label><Field name="handover_delivery_reference" label="Carrier / staff and confirmed recipient receipt evidence" maxLength={2000}/></>}
  <label className="grid gap-1 text-sm">Counseling outcome<select required name="counseling" defaultValue="" className="border rounded h-10 px-3 bg-background"><option value="">Select the recorded outcome</option><option value="provided">Provided</option><option value="declined">Declined</option><option value="documented_remote">Documented remote counseling</option></select></label>
  <Field name="handover_counseling_reference" label="Counseling / offer and refusal evidence reference" maxLength={2000}/>
  <label className="flex items-start gap-2 text-sm"><input required type="checkbox" name="handover_confirmed" className="mt-1"/>I verified the recipient and any representative authority, checked this fill and label, and confirmed actual receipt and the recorded counseling outcome.</label>
  <p className="text-xs text-muted-foreground">Synthetic preview only. These entries retain your checks; identity and delivery integrations and physical scanner acceptance are still pending.</p>
 </fieldset>
}

export function HandoverRecord({fulfillment}:{fulfillment:Record<string,unknown>&{handover?:HandoverEvidence}}) {
 const evidence=fulfillment.handover
 return <section className="border rounded p-3 space-y-2 text-sm break-words"><h4 className="font-semibold">Retained handover evidence</h4>
  <p>Handover date: {String(fulfillment.occurred_on||'Not recorded')} · Counseling: {String(fulfillment.counseling||'Not recorded').replaceAll('_',' ')}</p>
  <p>Completion reference: {String(fulfillment.reference||'Not recorded')}</p>
  {evidence?<><p>Recipient: {evidence.recipient_name} · {evidence.recipient_type==='patient'?'Patient':'Authorized representative'}</p><p>Identity checks: {evidence.identity_reference}</p>
   {evidence.recipient_type==='representative'&&<><p>Relationship: {evidence.relationship}</p><p>Authority: {evidence.authority_reference}</p></>}
   {evidence.delivery_method&&<p>Delivery: {evidence.delivery_method.replaceAll('_',' ')} · {evidence.delivery_reference}</p>}
   <p>Counseling evidence: {evidence.counseling_reference}</p><p>Recorded by pharmacist #{evidence.recorded_by} · {evidence.recorded_at}</p>
  </>:<p>This historical fill has no structured recipient evidence. No recipient verification has been inferred.</p>}
 </section>
}
