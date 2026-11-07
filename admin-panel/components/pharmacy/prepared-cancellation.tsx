'use client'

import {Field} from './fields'

export type PreparedCancellationEvidence = {
 custody: 'never_left_pharmacy'; quantity: string; evidence: string;
 segregation_reference: string; recorded_by: number; recorded_at: string;
 stock_receipt_id: number; stock_release_authorized: false;
}

export function cancellationInput(entries: Record<string, FormDataEntryValue>) {
 const prefix = 'cancellation_'
 return Object.fromEntries(Object.entries(entries).filter(([key]) => key.startsWith(prefix))
  .map(([key, value]) => [key.slice(prefix.length), key === `${prefix}confirmed` ? value === 'on' : value]))
}

export function PreparedCancellationFields({quantity, unit}: {quantity: string; unit: string}) {
 return <fieldset className="md:col-span-2 border rounded p-3 space-y-3 min-w-0">
  <legend className="font-semibold px-1">Account for prepared medication</legend>
  <p className="text-sm">Use this cancellation only when the entire preparation has remained at this pharmacy. Dispatched, missing or patient-returned medication cannot be resolved here; keep its reservation in place for custody investigation.</p>
  <input type="hidden" name="cancellation_custody" value="never_left_pharmacy"/>
  <Field name="cancellation_quantity" label={`Physically accounted quantity (${unit}; must equal ${quantity})`} type="number" min="0.001" step="0.001" max={quantity}/>
  <Field name="cancellation_evidence" label="Medication, quantity and continuous pharmacy custody checks" maxLength={2000}/>
  <Field name="cancellation_segregation_reference" label="Segregated storage location / custody reference" maxLength={2000}/>
  <label className="flex items-start gap-2 text-sm"><input name="cancellation_confirmed" type="checkbox" required/>I verified all prepared units remain at this pharmacy, have not been dispatched or handed over, and are segregated pending stock review.</label>
  <p className="text-sm font-medium">Cancellation releases the reservation but quarantines the entire source receipt if it is currently available. It does not approve stock reuse, disposal or billing.</p>
 </fieldset>
}

export function PreparedCancellationRecord({record, unit}: {record: PreparedCancellationEvidence; unit: string}) {
 return <section className="border rounded p-3 space-y-2 text-sm break-words">
  <h4 className="font-semibold">Retained preparation cancellation</h4>
  <p>Accounted quantity: {record.quantity} {unit} · Retained at the pharmacy</p>
  <p>Custody checks: {record.evidence}</p><p>Segregation: {record.segregation_reference}</p>
  <p>Recorded by pharmacist #{record.recorded_by} · {record.recorded_at}</p>
  <p>Stock receipt #{record.stock_receipt_id} requires its separate stock review. This cancellation did not authorize reuse.</p>
 </section>
}
