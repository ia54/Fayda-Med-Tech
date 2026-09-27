"use client"

import {useState} from 'react'
import {Field} from './fields'

export function scanInput(entries:Record<string,FormDataEntryValue>) {
 return Object.fromEntries(Object.entries(entries).filter(([key])=>key.startsWith('scan_')).map(([key,value])=>[key.slice(5),key==='scan_confirmed'?value==='on':value]))
}

export function FillCodeCheck({preparation}:{preparation:boolean}) {
 const [method,setMethod]=useState('')
 return <fieldset className="md:col-span-2 grid gap-3 min-w-0 border rounded p-3">
  <legend className="font-semibold px-1">{preparation?'Source package and label checks':'Handover label check'}</legend>
  <p className="text-sm">{preparation?'Check the actual source package, receipt lot and retained label. Enter codes exactly, including leading zeros.':'Check the label on the package being handed over against this prepared fill.'} A matching code does not replace pharmacist product, quantity or patient checks.</p>
  <label className="grid gap-1 text-sm">Code entry method<select name="scan_input_method" required value={method} onChange={e=>setMethod(e.target.value)} className="border rounded p-2 bg-background"><option value="">Select method</option><option value="scanner">Scanner</option><option value="manual">Manual comparison</option></select></label>
  {preparation&&<><Field name="scan_package_code" label="Source package GTIN (exact barcode text)" maxLength={14}/><Field name="scan_lot_number" label="Source package lot (exact receipt lot)" maxLength={100}/></>}
  <Field name="scan_label_code" label="Internal label code (FMTL-)" maxLength={64}/>
  {method==='manual'&&<Field name="scan_manual_reason" label="Why manual entry was needed and how the codes were checked" maxLength={2000}/>}
  <label className="flex gap-2 items-start text-sm"><input name="scan_confirmed" type="checkbox" required/>I checked these codes against the physical source and label for this fill. For this preview, I am recording a synthetic simulation.</label>
  <p className="text-xs text-muted-foreground">Use a keyboard-input scanner or record an explicit manual comparison. Hardware operation and physical print quality are not verified by this form.</p>
 </fieldset>
}

export function RetainedCodeChecks({fulfillment}:{fulfillment:Record<string,unknown>}) {
 const checks=[['Preparation',fulfillment.prepared_scan],['Handover',fulfillment.scan]] as const
 return <section className="border rounded p-3 space-y-2 text-sm break-words"><h4 className="font-semibold">Retained code checks</h4>{checks.map(([title,value])=>{
  if(!value||typeof value!=='object')return <p key={title}>{title}: no structured code check recorded.</p>
  const c=value as Record<string,unknown>
  return <div key={title} className="space-y-1"><p>{title} · {String(c.input_method)} · Pharmacist #{String(c.actor_id)} · {String(c.recorded_at)}</p><p>Label: {String(c.label_code)}</p>{Boolean(c.package_code)&&<p>Package: {String(c.package_code)} · Lot: {String(c.lot_number)}</p>}{Boolean(c.manual_reason)&&<p>Manual evidence: {String(c.manual_reason)}</p>}</div>
 })}</section>
}
