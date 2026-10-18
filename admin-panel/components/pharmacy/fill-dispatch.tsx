'use client'

import {useState} from 'react'
import Link from 'next/link'
import {Field,Choice} from './fields'
import {Button} from '@/components/ui/button'
import {useGetDispatchHistoryQuery,type PharmacyFill} from '@/store/api/pharmacyApiSlice'

export const transportActions=['dispatch','delivery_exception','return_from_dispatch','review_dispatch_return']
export function transportInput(entries:Record<string,FormDataEntryValue>){
 return Object.fromEntries(Object.entries(entries).filter(([k])=>k.startsWith('transport_')).map(([k,v])=>[k.slice(10),['transport_confirmed','transport_never_received_by_patient'].includes(k)?v==='on':v]))
}
export function DispatchFields({action,fill,unit}:{action:string;fill:PharmacyFill;unit:string}){
 return <fieldset className="md:col-span-2 border rounded p-3 space-y-3 min-w-0"><legend className="font-semibold px-1">Dispatch custody</legend>
 {action==='dispatch'&&<><p className="text-sm">Record the complete package leaving this pharmacy. Stock remains reserved until confirmed delivery or independent return review. This does not book a courier or notify anyone.</p><Choice name="transport_method" label="Delivery method" options={['pharmacy_staff','tracked_carrier']}/><Field name="transport_courier_reference" label="Courier / responsible staff and custody reference" maxLength={2000}/><Field name="transport_package_reference" label="Sealed package / tracking reference" maxLength={2000}/></>}
 {action==='delivery_exception'&&<><p className="text-sm">Keep all units reserved while investigating. No loss, return or successful delivery is inferred.</p><Choice name="transport_reason" label="Exception" options={['failed_delivery','missing_package','damaged_package']}/><Field name="transport_custodian_reference" label="Last known custodian / investigation reference" maxLength={2000}/></>}
 {action==='return_from_dispatch'&&<><p className="text-sm">Use only for the entire undelivered package that never reached the patient. Partial, missing or patient-returned quantities require separate investigation. Another assigned pharmacist must review this record before the reservation is resolved.</p><Field name="transport_label_code" label="Code read from the returned package label" maxLength={64}/><Field name="transport_package_condition" label="Observed package condition" maxLength={2000}/><Field name="transport_segregation_reference" label="Segregated storage / custody reference" maxLength={2000}/><label className="flex gap-2 text-sm"><input type="checkbox" name="transport_never_received_by_patient" required/>I verified that no medication in this package was received by the patient or their representative.</label></>}
 {['dispatch','return_from_dispatch'].includes(action)&&<Field name="transport_quantity" label={`Verified quantity (${unit}; must equal ${fill.quantity})`} type="number" min="0.001" step="0.001" max={fill.quantity}/>}
 {action==='review_dispatch_return'?<><input type="hidden" name="transport_return_id" value={fill.dispatch?.latest_return?.id||''}/><Choice name="transport_decision" label="Independent review decision" options={['reject','apply']}/><p className="text-sm">Apply only after independently checking the entire returned quantity and custody evidence. This cancels the unfulfilled fill and moves its units to a separate quarantine receipt for reviewed disposition. It never makes returned medication available for use.</p></>:<Field name="transport_occurred_on" label="Event date" type="date"/>}
 {action!=='dispatch'&&<Field name="transport_evidence" label="Evidence and review findings" maxLength={2000}/>}
 <label className="flex items-start gap-2 text-sm"><input type="checkbox" name="transport_confirmed" required/>I checked the package, quantity and supporting custody evidence for this action.</label>
 </fieldset>
}
export function DispatchRecord({rxId,fill}:{rxId:number;fill:PharmacyFill}){
 const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetDispatchHistoryQuery({rxId,fillId:fill.id,page},{skip:!fill.dispatch})
 const dispatch=fill.dispatch
 if(!dispatch)return null
 const history=currentData?.data.returns
 return <section className="border rounded p-3 space-y-3 text-sm break-words"><h4 className="font-semibold">Dispatch · {dispatch.status.replaceAll('_',' ')}</h4>
 <p>{dispatch.record.occurred_on} · {dispatch.record.quantity} units · {dispatch.record.method.replaceAll('_',' ')} · Recorded by pharmacist #{dispatch.created_by}</p>
 <p>Custodian: {dispatch.record.courier_reference}</p><p>Package: {dispatch.record.package_reference}</p>
 {dispatch.exception&&<div><p className="font-semibold">Exception · {dispatch.exception.reason.replaceAll('_',' ')} · {dispatch.exception.occurred_on}</p><p>{dispatch.exception.evidence}</p><p>Custodian: {dispatch.exception.custodian_reference}</p></div>}
 {['in_transit','exception','return_pending'].includes(dispatch.status)&&<p>All units remain reserved. Pickup and ordinary cancellation are blocked while dispatch custody is unresolved.</p>}
 {isError?<p role="alert">Return history could not be loaded. <Button type="button" variant="outline" onClick={()=>refetch()}>Retry</Button></p>:isFetching?<p role="status">Refreshing return history…</p>:<><h5 className="font-semibold">Return history</h5>{history?.data.map(r=><div key={r.id} className="border rounded p-3 space-y-2"><p>Return #{r.id} · {r.status} · Pharmacist #{r.created_by} · {r.created_at}</p><pre className="whitespace-pre-wrap break-words text-xs">{JSON.stringify(JSON.parse(r.record),null,2)}</pre>{r.reviewed_by&&<p>Review by pharmacist #{r.reviewed_by} · {r.reviewed_at}: {r.review_evidence}</p>}{r.returned_lot_id&&<Link className="underline" href={`/dashboard/pharmacy/stock/${r.returned_lot_id}`}>Open segregated quarantine receipt #{r.returned_lot_id}</Link>}</div>)}{history?.total===0&&<p>No return proposals recorded.</p>}<div className="flex gap-3 items-center"><Button type="button" variant="outline" disabled={page===1} onClick={()=>setPage(page-1)}>Previous returns</Button><span>Page {page} of {history?.last_page||1}</span><Button type="button" variant="outline" disabled={page>=(history?.last_page||1)} onClick={()=>setPage(page+1)}>Next returns</Button></div></>}
 </section>
}
