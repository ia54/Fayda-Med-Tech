'use client'
import Link from 'next/link'
import {useState} from 'react'
import {useRouter} from 'next/navigation'
import {PharmacyRx,RxTransferSnapshot,useCreateRxTransferMutation,useGetRxTransferDestinationsQuery,useGetRxTransferPreviewQuery} from '@/store/api/pharmacyApiSlice'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'

const holds:Record<string,string>={transfer_pending:'A transfer is already awaiting a decision.',controlled_transfer_unvalidated:'Controlled prescriptions require their dedicated transfer process.',compounded_transfer_unvalidated:'Compounded prescriptions require their dedicated transfer process.',prescription_discontinued:'The prescription is discontinued.',prescription_expired:'The prescription has expired.',historical_allowances_unresolved:'Historical authorization quantities need reconciliation.',allowance_correction_pending:'An allowance correction is awaiting independent review.',open_fill:'Resolve the open fill before transferring.',manufacturing_history:'Manufacturing history requires separate review.',allowances_exhausted:'No authorized quantity remains.'}
export function TransferQuantities({snapshot}:{snapshot:RxTransferSnapshot}){
 return <div className="space-y-2"><p className="font-medium">Remaining quantities to receive</p>{snapshot.allowances.length?<ul className="space-y-1">{snapshot.allowances.map(a=><li key={a.source_authorization_number}>{a.source_authorization_number===1?'Original allowance':`Refill ${a.source_authorization_number-1}`}: {a.quantity} {snapshot.quantity_unit}</li>)}</ul>:<p>No transferable quantities are available.</p>}<p className="text-sm">Each allowance stays separate. Clinical review, stock selection and preparation are required at the receiving location.</p></div>
}
export function PrescriptionTransfer({rx,role}:{rx:PharmacyRx;role:string}){
 const router=useRouter();const [destination,setDestination]=useState(''),[requestId,setRequestId]=useState(()=>crypto.randomUUID()),[error,setError]=useState('')
 const {currentData:sites,isFetching:loadingSites,isError:siteError,refetch:reloadSites}=useGetRxTransferDestinationsQuery(rx.id)
 const preview=useGetRxTransferPreviewQuery({id:rx.id,destination_location_id:Number(destination)},{skip:!destination||Boolean(rx.pending_transfer_id)||Boolean(rx.discontinued_at)})
 const [create,{isLoading}]=useCreateRxTransferMutation();const snapshot=preview.currentData?.data
 return <Card><CardHeader><CardTitle>Prescription transfer</CardTitle></CardHeader><CardContent className="space-y-4 min-w-0">
 <Link className="underline" href="/dashboard/pharmacy/prescription-transfers">Open prescription transfer worklist</Link>
 {rx.incoming_transfer_id&&<div className="border rounded p-3 space-y-2"><p>Received with transfer receipt #{rx.incoming_transfer_id}. Original order and source evidence remain retained.</p><ul>{Object.entries(rx.quantity_balance.authorization_limits).map(([n,q])=><li key={n}>Received allowance {n}: {q} {rx.quantity_unit}</li>)}</ul></div>}
 {rx.pending_transfer_id?<p role="status">Further supply is held while this transfer is pending. <Link className="underline" href={`/dashboard/pharmacy/prescription-transfers/${rx.pending_transfer_id}`}>Review transfer #{rx.pending_transfer_id}</Link></p>:rx.discontinued_at?<p>This prescription has been discontinued. Its existing history is retained.</p>:<>
 <p className="text-sm">Transfer remaining authorization to another pharmacy location in this organization. A request holds new fills at this location. A different receiving pharmacist must verify the patient, original source and transfer evidence before acceptance. This preview supports synthetic, noncontrolled, noncompounded records only.</p>
 {siteError?<p role="alert">Locations could not be loaded. <Button variant="outline" onClick={()=>reloadSites()}>Retry</Button></p>:<label className="grid gap-1 text-sm">Receiving pharmacy location<select className="border rounded p-2 bg-background w-full min-w-0" value={destination} disabled={loadingSites||isLoading} onChange={e=>{setDestination(e.target.value);setRequestId(crypto.randomUUID());setError('')}}><option value="">Select receiving location</option>{sites?.data.map(s=><option key={s.id} value={s.id}>{s.name}</option>)}</select></label>}
 {destination&&(preview.isError?<p role="alert">{errorMessage(preview.error)} <Button variant="outline" onClick={()=>preview.refetch()}>Refresh transfer review</Button></p>:!snapshot?<p role="status">Checking the retained order and quantities…</p>:<>
 <p>{snapshot.identity.patient.first_name} {snapshot.identity.patient.last_name} · Case {snapshot.identity.case.case_number}</p>
 <TransferQuantities snapshot={snapshot}/>
 {snapshot.holds.map(h=><p key={h} role="status">{holds[h]||h.replaceAll('_',' ')}</p>)}
 {!snapshot.source_documents.length&&<p>Attach the original prescription under Original prescription evidence before requesting transfer.</p>}
 {role==='pharmacist'&&!snapshot.holds.length&&snapshot.source_documents.length>0&&<form key={destination} className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');const input=Object.fromEntries(new FormData(e.currentTarget));try{const result=await create({id:rx.id,body:{...input,request_id:requestId,destination_location_id:Number(destination),source_token:snapshot.source_token,confirmed:true}}).unwrap();router.push(`/dashboard/pharmacy/prescription-transfers/${result.data.id}`)}catch(e){setError(errorMessage(e))}}}>
 <Field name="sending_evidence" label="Patient request and sending pharmacist transfer evidence" maxLength={5000}/>
 <Field name="sharing_reference" label="Patient identity and authority to share with this location" maxLength={2000}/>
 <label key={snapshot.source_token} className="flex gap-2 items-start text-sm"><input type="checkbox" required/>I verified this patient, the retained original prescription and authority to share it. I understand that further supply here will be held until the request is resolved.</label>
 <Button className="h-auto whitespace-normal" disabled={isLoading||preview.isFetching||loadingSites}>Request transfer and hold source supply</Button>
 </form>}
 </>)}
 </>}{error&&<p role="alert">{error}</p>}
 </CardContent></Card>
}
