"use client"
import {FormEvent,useRef,useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {CompoundBatch,useGetCompoundingIncidentsQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function CompoundingIncidents({batch}:{batch:CompoundBatch}) {
 const {user}=useAuth();const [page,setPage]=useState(1)
 const {currentData,isFetching,isError,refetch}=useGetCompoundingIncidentsQuery({id:batch.id,page})
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('');const [success,setSuccess]=useState('')
 const request=useRef<{body:string;id:string}|null>(null)
 const records=currentData?.data.data||[]
 const canRecord=user?.role==='pharmacist'&&!batch.execution&&batch.allocations.length>0&&batch.allocations.every(a=>a.status==='reserved')&&currentData?.data.total===0
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');setSuccess('');const form=e.currentTarget;const values=new FormData(form)
  const body={version:batch.version,observed_at:String(values.get('observed_at')).replace('T',' ')+':00',findings:values.get('findings'),custody_evidence:values.get('custody_evidence'),follow_up_owner:values.get('follow_up_owner'),ingredients:batch.allocations.map(a=>({allocation_id:a.id,observed_quantity:values.get(`unknown-${a.id}`)==='on'?null:String(values.get(`quantity-${a.id}`)),measurement_evidence:values.get(`evidence-${a.id}`)}))}
  const serialized=JSON.stringify(body);if(request.current?.body!==serialized)request.current={body:serialized,id:crypto.randomUUID()}
  try {await save({path:`batch-worksheets/${batch.id}/incidents`,body:{...body,request_id:request.current.id}}).unwrap();setSuccess('Incident retained. Ingredient custody is held; no stock consumption or output release was recorded.');form.reset()}catch(e){setError(errorMessage(e))}
 }
 return <section className="border-t pt-4 space-y-4"><h3 className="font-semibold">Preparation incidents and custody holds</h3>
 <p className="text-sm">Record uncertain or differing ingredient consumption before an execution record exists. An incident holds the affected receipts and reservations for reconciliation. It does not authorize further preparation or dispensing.</p>
 {success&&<p role="status">{success}</p>}
 {isError?<p role="alert">Could not load incident history. <Button type="button" onClick={()=>refetch()}>Retry</Button></p>:isFetching?<p>Loading incident history…</p>:records.length?records.map(record=><article key={record.id} className="border rounded p-3 space-y-2"><strong>Incident {record.id} · {record.status}</strong><p>Observed {record.observed_at} UTC</p><p className="whitespace-pre-wrap">{record.findings}</p><p className="whitespace-pre-wrap">Custody: {record.custody_evidence}</p><p>Follow-up owner: {record.follow_up_owner}</p>{record.ingredients.map(line=><div key={line.allocation_id}><p>Ingredient {line.ingredient_key} · receipt {line.ingredient_lot_id}: {line.observed_quantity===null?'Unknown consumption':`${line.observed_quantity} ${line.quantity_unit} observed consumption`}</p><p className="whitespace-pre-wrap">{line.measurement_evidence}</p></div>)}</article>):<p>No preparation incidents recorded.</p>}
 {(currentData?.data.last_page||1)>1&&<div className="flex gap-3"><Button type="button" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous incidents</Button><span>Page {page}</span><Button type="button" disabled={isFetching||page>=(currentData?.data.last_page||1)} onClick={()=>setPage(page+1)}>Next incidents</Button></div>}
 {canRecord&&!isError&&<form onSubmit={submit} className="space-y-3"><fieldset disabled={isLoading||isFetching} className="space-y-3"><Field name="observed_at" label="Observed at (UTC)" type="datetime-local"/>{['findings','custody_evidence','follow_up_owner'].map(name=><Field key={name} name={name} label={{findings:'What happened and what remains uncertain',custody_evidence:'Material custody and hold evidence',follow_up_owner:'Responsible follow-up owner'}[name]||name}/>)}{batch.allocations.map(a=><Observation key={a.id} id={a.id} label={`${a.ingredient_key} · receipt ${a.ingredient_lot_id} · ${a.quantity_unit}`}/>)}{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||isFetching}>Retain incident and hold ingredients</Button></fieldset></form>}
 </section>
}
function Observation({id,label}:{id:number;label:string}) {
 const [unknown,setUnknown]=useState(true)
 return <fieldset className="border rounded p-3 space-y-2"><legend>{label}</legend><label className="flex gap-2"><input name={`unknown-${id}`} type="checkbox" checked={unknown} onChange={e=>setUnknown(e.target.checked)}/>Consumption is unknown</label>{!unknown&&<label className="grid gap-1">Observed consumed quantity (enter 0 only if measured as zero)<input name={`quantity-${id}`} required type="number" min="0" max="999999.999" step="0.001" className="border rounded p-2 bg-background"/></label>}<Field name={`evidence-${id}`} label="Measurement or uncertainty evidence"/></fieldset>
}
