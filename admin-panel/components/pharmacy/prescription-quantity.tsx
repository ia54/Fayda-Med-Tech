"use client"
import {useState} from 'react'
import type {PharmacyRx} from '@/store/api/pharmacyApiSlice'
import {Field,Choice} from '@/components/pharmacy/fields'
import {Button} from '@/components/ui/button'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
const allowance=(number:number)=>number===1?'Original allowance':`Refill ${number-1}`
export function PrescriptionQuantity({rx,role,disabled,save}:{rx:PharmacyRx;role:string;disabled:boolean;save:(d:Record<string,unknown>)=>void}){
 const b=rx.quantity_balance
 const label=(number:number)=>b.incoming_transfer?`Received allowance ${number}`:allowance(number)
 return <Card><CardHeader><CardTitle>Authorized quantity</CardTitle></CardHeader><CardContent className="space-y-3">
  <p>{b.incoming_transfer?"This prescription retains the separate remaining allowances accepted from the sending location. Each received allowance keeps its own authorized quantity.":`Each original or refill allowance is ${rx.quantity} ${rx.quantity_unit}.`} Each pickup or delivery retains its own pharmacist review and billing record.</p>
  {b.legacy_allowances_used>0&&<p>{b.legacy_allowances_used} historical or restricted fill record(s) count as whole allowances. No extra quantity is inferred from those records.</p>}
  {b.allowances.map(a=><div key={a.number} className="border rounded p-3 space-y-2 break-words">
   <h3 className="font-semibold">{label(a.number)}</h3>
   <p>Authorized: {a.authorized} {rx.quantity_unit}</p>
   <p>Handed over: {a.handed_over} · Reserved: {a.reserved} · Unreserved remainder: {a.remaining} {rx.quantity_unit}</p>
   {a.closure&&<div className="border-t pt-2 space-y-2"><p className="font-semibold">Closed without supply: {a.closed_quantity} {rx.quantity_unit}</p><p>Basis: {a.closure.basis.replaceAll('_',' ')} · Effective {a.closure.occurred_on}</p><p>{a.closure.reason}</p><p>Evidence: {a.closure.evidence}</p><p className="text-sm text-muted-foreground">Recorded by pharmacist #{a.closure.actor_id} · {a.closure.created_at}. Stock and prior handovers are unchanged. This remainder is unavailable unless a retained correction is independently applied.</p></div>}
  </div>)}
  {b.pending_transfer_id?<p>New reservations are held pending resolution of the prescription transfer.</p>:b.pending_correction_id?<p>New reservations are held pending independent allowance-correction review.</p>:rx.discontinued_at?<p>This prescription is discontinued. The quantities above are retained history and cannot authorize another supply.</p>:b.next_authorization_number?<p>Available for a future reservation: {b.available_quantity} {rx.quantity_unit} under {label(b.next_authorization_number).toLowerCase()}. Resolve any open fill first.</p>:<p>No authorized allowances remain.</p>}
  <p className="text-sm text-muted-foreground">Remaining quantity does not establish that dispensing is clinically appropriate or due. A pharmacist must verify every supply. Allowances cannot be pooled, and historical partial quantities cannot be reclaimed here.</p>
  {!rx.discontinued_at&&role==='pharmacist'&&b.closable_authorization_number!==null&&<CloseRemainder key={b.ledger_token} number={b.closable_authorization_number} label={label(b.closable_authorization_number)} quantity={b.available_quantity} unit={rx.quantity_unit} token={b.ledger_token} disabled={disabled} save={save}/>}
 </CardContent></Card>
}
function CloseRemainder({number,label,quantity,unit,token,disabled,save}:{number:number;label:string;quantity:string;unit:string;token:string;disabled:boolean;save:(d:Record<string,unknown>)=>void}){
 const [requestId]=useState(()=>crypto.randomUUID())
 return <details className="rounded border p-3"><summary className="cursor-pointer underline">Close remaining allowance</summary>
  <form className="grid gap-3 mt-3" onSubmit={e=>{e.preventDefault();save({...Object.fromEntries(new FormData(e.currentTarget)),request_id:requestId,ledger_token:token,authorization_number:number,confirmed:true})}}>
   <p>Close the unsupplied {quantity} {unit} from {label.toLowerCase()} only after reviewing the patient’s request or prescriber’s instruction. The original decision is retained. Correcting an error requires a separate request and independent pharmacist review. It does not dispense, return or destroy stock, cancel the prescription, or authorize an early refill. Any later supply needs its own pharmacist review.</p>
   <Choice name="basis" label="Supporting authority" options={['patient_request','prescriber_instruction']}/>
   <Field name="occurred_on" label="Decision date" type="date"/>
   <Field name="reason" label="Reason for closing this remainder" maxLength={2000}/>
   <Field name="evidence" label="Patient request / prescriber instruction evidence" maxLength={2000}/>
   <label className="flex items-start gap-2 text-sm"><input type="checkbox" required/>I reviewed the authority and quantity. This closes the unsupplied remainder without moving it to another allowance.</label>
   <Button variant="destructive" disabled={disabled}>Close {quantity} {unit} without supply</Button>
  </form>
 </details>
}
