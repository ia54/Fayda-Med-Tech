'use client'
import Link from 'next/link'
import type {ReactNode} from 'react'
import type {PharmacyQueueItem} from '@/store/api/pharmacyApiSlice'

type Props={rows:PharmacyQueueItem[];locations:{id:number;name:string}[];role:string}
function Signals({rx,role}:{rx:PharmacyQueueItem;role:string}){
 return <div className="space-y-1 text-sm">
  {rx.patient_return_count>0&&<p className="font-semibold">Patient-return custody: {rx.patient_return_count}</p>}
  {Number(rx.pending_handover_count)>0&&<p className="font-semibold">Handover corrections pending: {rx.pending_handover_count}{role==='pharmacist'&&<span className="block text-xs">Available for your independent review: {rx.reviewable_handover_count}</span>}</p>}
  {Number(rx.pending_correction_count)>0&&<p className="font-semibold">Allowance correction pending · new fills held</p>}
  {rx.discontinued_at&&<div><p className="font-semibold text-destructive">Discontinued</p>{(Number(rx.open_fill_count)>0||Number(rx.reserved_batch_count)>0)&&<p className="text-xs">Needs resolution: {rx.open_fill_count} open fills · {rx.reserved_batch_count} batches with reservations</p>}</div>}
 </div>
}
function Medication({rx}:{rx:PharmacyQueueItem}){
 return <><p>{rx.medication} {rx.strength}</p><p className="text-xs">{Boolean(rx.controlled)&&'Controlled · '}{Boolean(rx.compounded)&&(rx.compound_type?`${rx.compound_type==='sterile'?'Sterile':'Nonsterile'} compound`:'Compound · classification pending')}</p></>
}
function LatestFill({rx}:{rx:PharmacyQueueItem}){
 return <><p>{rx.dispatch_status?`Dispatch: ${rx.dispatch_status.replaceAll('_',' ')}`:rx.stage.replaceAll('_',' ')}</p><p className="text-xs">Review: {rx.review_status?.replaceAll('_',' ')||'Not recorded'}</p><p className="text-xs">Claim: {rx.claim_status?.replaceAll('_',' ')||'Not recorded'}</p></>
}
function Actions({rx,role}:{rx:PharmacyQueueItem;role:string}){
 const style='flex min-h-11 max-w-full items-center rounded-sm py-2 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary [overflow-wrap:anywhere]'
 return <div className="space-y-1"><Link className={style} href={`/dashboard/pharmacy/${rx.id}`}>Open {rx.rx_number}</Link>
  {rx.patient_return_fill_id&&<Link className={style} href={`/dashboard/pharmacy/${rx.id}#fill-${rx.patient_return_fill_id}`}>Review patient-return custody</Link>}
  {Number(rx.pending_handover_count)>0&&<Link className={style} href={`/dashboard/pharmacy/${rx.id}#fill-${role==='pharmacist'&&rx.reviewable_handover_fill_id?rx.reviewable_handover_fill_id:rx.pending_handover_fill_id}`}>{role==='pharmacist'&&Number(rx.reviewable_handover_count)>0?'Review pending handover correction':'View pending handover correction'}</Link>}
 </div>
}
function Field({label,children}:{label:string;children:ReactNode}){return <div className="min-w-0"><dt className="text-xs font-semibold text-muted-foreground">{label}</dt><dd className="mt-1 [overflow-wrap:anywhere]">{children}</dd></div>}

/** Every layout retains the same identity, safety signals, statuses and authorized links. */
export function PrescriptionWorklist({rows,locations,role}:Props){
 const location=(rx:PharmacyQueueItem)=>locations.find(l=>l.id===rx.location_id)?.name||`Location #${rx.location_id}`
 return <>
  <ul aria-label="Prescription worklist" className="space-y-4 xl:hidden">
   {rows.map(rx=><li key={rx.id}><article aria-labelledby={`worklist-rx-${rx.id}`} data-pharmacy-record={rx.id} className="min-w-0 space-y-3 rounded-lg border bg-card p-4 text-sm [overflow-wrap:anywhere]">
    <h3 id={`worklist-rx-${rx.id}`} className="font-semibold text-base">{rx.rx_number}</h3><Signals rx={rx} role={role}/>
    <dl className="grid min-w-0 gap-3 sm:grid-cols-2"><Field label="Pharmacy location">{location(rx)}</Field><Field label="Patient / accident case"><p>{rx.first_name} {rx.last_name}</p><p className="text-xs text-muted-foreground">{rx.case_number}</p></Field><Field label="Medication"><Medication rx={rx}/></Field><Field label="Latest fill"><LatestFill rx={rx}/></Field><Field label="Coverage">{rx.coverage_status.replaceAll('_',' ')}</Field></dl>
    <div className="border-t pt-1"><Actions rx={rx} role={role}/></div>
   </article></li>)}
  </ul>
  <div className="hidden xl:block">
   <table data-pharmacy-worklist="true" className="w-full table-fixed text-sm"><caption className="sr-only">Prescription worklist</caption><thead><tr>{['Prescription / location','Patient / accident case','Medication','Latest fill','Coverage','Action'].map(label=><th key={label} scope="col" className="border-b p-3 text-left align-top font-medium [overflow-wrap:anywhere]">{label}</th>)}</tr></thead>
    <tbody>{rows.map(rx=><tr key={rx.id} data-pharmacy-record={rx.id} className="border-b last:border-0 hover:bg-muted/50 [&>td]:min-w-0 [&>td]:p-3 [&>td]:align-top [&>td]:[overflow-wrap:anywhere]">
     <td><p className="font-medium">{rx.rx_number}</p><Signals rx={rx} role={role}/><p className="text-xs text-muted-foreground">{location(rx)}</p></td><td>{rx.first_name} {rx.last_name}<p className="text-xs text-muted-foreground">{rx.case_number}</p></td><td><Medication rx={rx}/></td><td><LatestFill rx={rx}/></td><td>{rx.coverage_status.replaceAll('_',' ')}</td><td><Actions rx={rx} role={role}/></td>
    </tr>)}</tbody>
   </table>
  </div>
 </>
}
