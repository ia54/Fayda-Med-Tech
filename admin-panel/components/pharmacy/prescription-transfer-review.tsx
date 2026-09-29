'use client'
import Link from 'next/link'
import {useEffect,useState} from 'react'
import {useSelector} from 'react-redux'
import {RootState} from '@/store/store'
import {RxTransfer,RxTransferSnapshot,useGetRxTransferQuery,useGetPharmacyLocationsQuery,useReviewRxTransferMutation} from '@/store/api/pharmacyApiSlice'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {TransferQuantities} from './prescription-transfer'

function TransferFile({id,source}:{id:number;source:RxTransferSnapshot["source_documents"][number]}){
 const token=useSelector((s:RootState)=>s.auth.token?.access_token)
 const [download,setDownload]=useState<{url:string;token:string;requestId:number;sourceId:number}|null>(null),[error,setError]=useState('')
 useEffect(()=>{setDownload(null);setError('');if(!token)return;const controller=new AbortController();let url=''
 const base=process.env.NEXT_PUBLIC_API_BASE_URL||'http://localhost:8000/api'
 fetch(`${base.replace(/\/$/,'')}/pharmacy/prescription-transfers/${id}/sources/${source.id}/file`,{headers:{Authorization:`Bearer ${token}`},cache:'no-store',signal:controller.signal})
 .then(async r=>{if(!r.ok)throw new Error('Access or source-file integrity could not be verified. Refresh the transfer and contact the sending pharmacist.');return r.blob()})
 .then(blob=>{if(!controller.signal.aborted){url=URL.createObjectURL(blob);setDownload({url,token,requestId:id,sourceId:source.id})}})
 .catch(e=>{if(!controller.signal.aborted)setError(e.message)})
 return()=>{controller.abort();if(url)URL.revokeObjectURL(url)}
 },[id,source.id,token])
 return error?<p role="alert">{error}</p>:download?.token===token&&download?.requestId===id&&download?.sourceId===source.id?<a className="underline block" href={download.url} download={source.original_name}>Download {source.original_name}</a>:<p role="status">Checking access and original-file integrity…</p>
}

function TransferDecision({transfer,decisions,disabled}:{transfer:RxTransfer;decisions:string[];disabled:boolean}){
 const [decision,setDecision]=useState(decisions[0]),[requestId,setRequestId]=useState(()=>crypto.randomUUID()),[error,setError]=useState('')
 const [review,{isLoading}]=useReviewRxTransferMutation()
 return <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');const body=Object.fromEntries(new FormData(e.currentTarget));try{await review({id:transfer.id,body:{...body,request_id:requestId,decision,source_token:transfer.source_token,confirmed:true}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <label className="grid gap-1 text-sm">Transfer decision<select className="border rounded p-2 bg-background" value={decision} disabled={disabled||isLoading} onChange={e=>{setDecision(e.target.value);setRequestId(crypto.randomUUID());setError('')}}>{decisions.map(d=><option key={d} value={d}>{d==='accept'?'Accept at receiving pharmacy':d==='reject'?'Reject as receiving pharmacist':'Cancel sending request'}</option>)}</select></label>
 {decision==='accept'&&<Field name="rx_number" label="New prescription number at receiving pharmacy" maxLength={100}/>}
 <Field name="evidence" label={decision==='accept'?'Receiving pharmacist verification of original source, patient/case identity and transfer authority':'Reason and supporting evidence for this decision'} maxLength={5000}/>
 <label key={`${decision}-${transfer.source_token}`} className="flex gap-2 items-start text-sm"><input type="checkbox" required/>{decision==='accept'?'I independently reviewed the original files, patient/case identity, sharing authority and each remaining allowance. Acceptance retires source supply and creates a receiving record; it does not approve clinical review or dispensing.':'I reviewed this request and the supporting evidence. This decision will be retained and will release the transfer hold without creating receiving authority.'}</label>
 <Button className="h-auto whitespace-normal" disabled={disabled||isLoading}>{decision==='accept'?'Accept transfer and create receiving record':decision==='reject'?'Retain rejection':'Cancel transfer request'}</Button>
 {error&&<p role="alert">{error}</p>}
 </form>
}

export function PrescriptionTransferReview({id,actorId,role}:{id:number;actorId:number;role:string}){
 const {currentData,isFetching,isError,refetch}=useGetRxTransferQuery(id)
 const {currentData:sites,isFetching:loadingSites,isError:siteError}=useGetPharmacyLocationsQuery()
 const [selected,setSelected]=useState<number|null>(null);const t=currentData?.data
 if(isError)return <p role="alert">Transfer access or retrieval failed. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>
 if(!t)return <p role="status">Loading prescription transfer…</p>
 const has=(id:number)=>Boolean(sites?.data.some(s=>s.id===id));const canReceive=has(t.destination_location_id)&&actorId!==Number(t.sent_by);const canSend=has(t.source_location_id)
 const decisions=role==='pharmacist'?[...(canReceive?['accept','reject']:[]),...(canSend?['cancel']:[])]:[]
 const snapshot=t.source_snapshot,patient=snapshot.identity.patient,order=snapshot.order
 return <div className="space-y-5 min-w-0"><h1 className="text-3xl font-bold text-primary">Prescription transfer #{t.id}</h1><p className="font-semibold">{t.status.charAt(0).toUpperCase()+t.status.slice(1)} · {t.source_location_name} → {t.destination_location_name}</p>
 <p className="rounded border p-3 text-sm">Synthetic development preview. Controlled and compounded transfers remain unavailable. No prescriber, payer, MAPS or AI transmission is performed.</p>
 <Card><CardHeader><CardTitle>Retained sending record</CardTitle></CardHeader><CardContent className="space-y-3 break-words"><p>{patient.first_name} {patient.last_name}{patient.date_of_birth?` · Born ${patient.date_of_birth}`:''} · Case {snapshot.identity.case.case_number}</p><p>{String(order.rx_number)} · {String(order.medication)} · {String(order.strength)} · {String(order.dosage_form)}</p><p>Directions: {String(order.directions)}</p><p>Written {String(order.written_on)} · Expires {String(order.expires_on)}</p><p>Prescriber: {String(order.prescriber_name)} · {String(order.prescriber_identifier)}</p><TransferQuantities snapshot={snapshot}/><p>Sending pharmacist #{t.sent_by} · {t.created_at}</p><p>Sending evidence: {t.sending_evidence}</p><p>Sharing authority: {t.sharing_reference}</p>{canSend&&<Link className="underline" href={`/dashboard/pharmacy/${t.source_prescription_id}`}>Open retained source prescription</Link>}</CardContent></Card>
 <Card><CardHeader><CardTitle>Original prescription evidence</CardTitle></CardHeader><CardContent className="space-y-3">{snapshot.source_documents.map(source=><div key={source.id} className="rounded border p-3 space-y-2 break-words"><p>{source.original_name}</p><p>{source.reference}</p><Button variant="outline" onClick={()=>setSelected(selected===source.id?null:source.id)}>{selected===source.id?'Close original-file access':'Retrieve original prescription file'}</Button>{selected===source.id&&<TransferFile id={id} source={source}/>}</div>)}</CardContent></Card>
 {t.status==='pending'?<Card><CardHeader><CardTitle>Resolve transfer request</CardTitle></CardHeader><CardContent className="space-y-3"><p>Further source supply is held. Changed patient, case, order or original-file evidence prevents acceptance; reject or cancel a stale request and have it reassessed.</p>{siteError?<p role="alert">Current location authority could not be loaded. Refresh before deciding.</p>:!sites?<p role="status">Checking current location assignments…</p>:decisions.length?<TransferDecision key={decisions.join('-')} transfer={t} decisions={decisions} disabled={isFetching||loadingSites}/>:<p>{role==='pharmacist'?'A different pharmacist with current receiving-location access must review this request.':'A pharmacist must record the transfer decision.'}</p>}</CardContent></Card>:<Card><CardHeader><CardTitle>Retained decision</CardTitle></CardHeader><CardContent className="space-y-3 break-words"><p>{t.status} · Pharmacist #{t.reviewed_by} · {t.reviewed_at}</p><p>{t.review_evidence}</p>{t.receipt_id&&<p>Transfer receipt #{t.receipt_id}. Original evidence and quantity limits are retained.</p>}{t.destination_prescription_id&&has(t.destination_location_id)&&<Link className="underline" href={`/dashboard/pharmacy/${t.destination_prescription_id}`}>Open receiving prescription for clinical review</Link>}</CardContent></Card>}
 </div>
}
