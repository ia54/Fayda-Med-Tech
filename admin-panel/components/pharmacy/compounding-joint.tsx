"use client"
import {FormEvent,ReactNode,useRef,useState} from 'react'
import {CompoundingJointReview} from './compounding-joint-review'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {useGetJointIncidentHistoryQuery,useGetJointIncidentPreviewQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'

export function CompoundingJointDiscovery({incidentId}:{incidentId:number}) {
 const {user}=useAuth();const [opened,setOpened]=useState(false)
 const {currentData,isFetching,isError,refetch}=useGetJointIncidentPreviewQuery(incidentId,{skip:!opened})
 const [save,{isLoading}]=usePharmacyStockActionMutation();const [error,setError]=useState('')
 const request=useRef<{body:string;id:string}|null>(null);const data=currentData?.data
 async function submit(e:FormEvent<HTMLFormElement>) {
  e.preventDefault();setError('');const evidence=String(new FormData(e.currentTarget).get('evidence'));const body=JSON.stringify({evidence})
  if(request.current?.body!==body)request.current={body,id:crypto.randomUUID()}
  try {await save({path:`incidents/${incidentId}/joint-groups`,body:{evidence,request_id:request.current.id}}).unwrap()}catch(e){setError(errorMessage(e))}
 }
 return <section className="border-t pt-3 space-y-3"><h4 className="font-semibold">Shared ingredient incidents</h4>
 <p>Related incidents using the same ingredient receipts must be reconciled together. Grouping retains the investigation evidence and leaves stock held.</p>
 {!opened?<Button type="button" variant="outline" onClick={()=>setOpened(true)}>Find related incidents</Button>:isError?<p role="alert">Could not load related incidents. <Button type="button" onClick={()=>refetch()}>Retry related incidents</Button></p>:!data?<p>Finding related incidents…</p>:<>
 <p>Related incidents: {data.incident_ids.join(', ')} · location {data.location_id}</p>
 {data.ingredients.map(line=><p key={line.allocation_id}>Incident {line.incident_id} · receipt {line.ingredient_lot_id} · original reservation {line.reserved_quantity} {line.quantity_unit}</p>)}
 {data.active_group_id?<CompoundingJointReview id={data.active_group_id}/>:data.incident_ids.length<2?<p>No other unresolved incidents share these receipts. Use the individual reconciliation below.</p>:user?.role==='pharmacist'&&<form onSubmit={submit}><fieldset disabled={isLoading||isFetching} className="space-y-3"><Field name="evidence" label="Why these incidents require a joint investigation" maxLength={5000}/>{error&&<p role="alert">{error}</p>}<Button disabled={isLoading||isFetching}>Retain joint investigation group</Button></fieldset></form>}
 </>}
 </section>
}

export function CompoundingJointHistory({incidentId,reconciled,children}:{incidentId:number;reconciled:boolean;children:ReactNode}) {
 const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null)
 const {currentData,isFetching,isError,refetch}=useGetJointIncidentHistoryQuery({id:incidentId,page})
 if(isError)return <p role="alert">Could not load joint investigations. <Button type="button" onClick={()=>refetch()}>Retry joint investigations</Button></p>
 if(!currentData)return <p>Loading joint investigations…</p>
 const groups=currentData.data.data;const active=groups.find(g=>!['rejected','reconciled'].includes(g.status));const chosen=selected??active?.id
 return <section className="space-y-3 border-t pt-3"><h4 className="font-semibold">Joint investigation history</h4>{groups.map(g=><Button key={g.id} type="button" variant="outline" onClick={()=>setSelected(g.id)}>Open group {g.id} · {g.status}</Button>)}
 {chosen&&<CompoundingJointReview key={chosen} id={chosen}/>}
 {currentData.data.last_page>1&&<div className="flex gap-3"><Button type="button" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous groups</Button><span>Page {page}</span><Button type="button" disabled={page>=currentData.data.last_page||isFetching} onClick={()=>setPage(page+1)}>Next groups</Button></div>}
 {!reconciled&&!currentData.joint_managed&&page===1&&<CompoundingJointDiscovery incidentId={incidentId}/>}
 {!groups.length&&reconciled&&<p>No joint investigation records.</p>}
 {!currentData.joint_managed&&children}
 </section>
}
