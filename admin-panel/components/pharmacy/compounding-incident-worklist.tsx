"use client"
import {useRouter,useSearchParams} from 'next/navigation'
import Link from 'next/link'
import {Button} from '@/components/ui/button'
import {useGetCompoundingIncidentWorklistQuery,useGetPharmacyLocationsQuery} from '@/store/api/pharmacyApiSlice'
const statuses:Record<string,string>={unresolved:'Investigation and accounting required',accounted_custody_held:'Unused-material custody review required',reconciled:'Reconciled — no product release'}
export function CompoundingIncidentWorklist(){
 const params=useSearchParams();const router=useRouter()
 const rawPage=Number(params.get('incidentPage')||1);const page=Number.isSafeInteger(rawPage)&&rawPage>0?rawPage:1
 const rawStatus=params.get('incidentStatus')||'open';const status=['open','reconciled','all'].includes(rawStatus)?rawStatus:'open'
 const rawLocation=Number(params.get('incidentLocation'));const location=Number.isSafeInteger(rawLocation)&&rawLocation>0?String(rawLocation):''
 function filter(key:string,value:string){const next=new URLSearchParams(params.toString());next.set(key,value);if(key!=='incidentPage')next.set('incidentPage','1');router.replace(`/dashboard/pharmacy/compounding?${next}`,{scroll:false})}
 function worksheet(id:number){const next=new URLSearchParams(params.toString());next.set('view','batch');next.set('id',String(id));return `/dashboard/pharmacy/compounding?${next}`}

 const {currentData,isFetching,isError,refetch}=useGetCompoundingIncidentWorklistQuery({page,status,location_id:location?Number(location):undefined})
 const {data:locations,isError:locationError}=useGetPharmacyLocationsQuery()
 return <section className="border rounded-lg p-5 space-y-4"><h2 className="text-xl font-semibold">Compounding incident follow-up</h2><p>Oldest incidents appear first. Review original observations, accounting and custody decisions on the linked worksheet. Reconciliation does not authorize preparation or product release.</p>
 <div className="flex flex-wrap gap-4"><label className="grid gap-1">Incident status<select className="border rounded p-2 bg-background" value={status} onChange={e=>filter('incidentStatus',e.target.value)}><option value="open">Unresolved incidents</option><option value="reconciled">Reconciled incidents</option><option value="all">All incidents</option></select></label><label className="grid gap-1">Incident location<select className="border rounded p-2 bg-background" value={location} onChange={e=>filter('incidentLocation',e.target.value)}><option value="">All assigned locations</option>{locations?.data.filter(l=>l.active).map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label></div>
 {locationError&&<p role="alert">Location names could not be loaded. Results remain limited to your current assignments.</p>}
 {isError?<p role="alert">Could not load incidents. <Button onClick={()=>refetch()}>Retry incident list</Button></p>:!currentData?<p>Loading incidents…</p>:<><p>{currentData.data.total} matching incidents{isFetching?' · Refreshing…':''}</p>{currentData.data.data.map(i=><article className="border-t py-3 space-y-1" key={i.id}><Link className="underline font-medium" href={worksheet(i.batch_id)}>Incident {i.id} · {i.batch_number}</Link><p>{statuses[i.status]||i.status} · {locations?.data.find(l=>l.id===i.location_id)?.name||`Location ${i.location_id}`}</p><p>Follow-up owner: {i.follow_up_owner}</p><p>Observed: {i.observed_at} UTC</p></article>)}{!currentData.data.total&&<p>No incidents match these filters.</p>}<div className="flex gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>filter('incidentPage',String(page-1))}>Previous incidents</Button><span>Page {page} / {currentData.data.last_page}</span><Button variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>filter('incidentPage',String(page+1))}>Next incidents</Button></div></>}
 </section>
}
