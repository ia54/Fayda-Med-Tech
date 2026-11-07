"use client"
import Link from 'next/link'
import {useRouter,useSearchParams} from 'next/navigation'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {useGetYieldCorrectionWorklistQuery,useGetPharmacyLocationsQuery} from '@/store/api/pharmacyApiSlice'

export function YieldCorrectionWorklist(){
 const params=useSearchParams();const router=useRouter();const {user}=useAuth()
 const rawPage=Number(params.get('yieldPage')||1);const page=Number.isSafeInteger(rawPage)&&rawPage>0?rawPage:1
 const rawLocation=Number(params.get('yieldLocation'));const location=Number.isSafeInteger(rawLocation)&&rawLocation>0?String(rawLocation):''
 const reviewable=user?.role==='pharmacist'&&params.get('yieldReviewable')==='1'
 const {currentData,isFetching,isError,refetch}=useGetYieldCorrectionWorklistQuery({page,location_id:location?Number(location):undefined,reviewable:reviewable?1:0})
 const {data:locations,isError:locationError}=useGetPharmacyLocationsQuery()
 function filter(key:string,value:string){const next=new URLSearchParams(params.toString());next.set(key,value);if(key!=='yieldPage')next.set('yieldPage','1');router.replace(`/dashboard/pharmacy/compounding?${next}`,{scroll:false})}
 function worksheet(id:number){const next=new URLSearchParams(params.toString());next.set('view','batch');next.set('id',String(id));return `/dashboard/pharmacy/compounding?${next}`}
 return <section className="border rounded-lg p-5 space-y-4">
  <h2 className="text-xl font-semibold">Pending yield correction reviews</h2>
  <p>Review proposed yield corrections and their supporting evidence on the linked batch. Oldest proposals appear first. Decisions do not release medication.</p>
  <label className="grid gap-1">Yield correction location<select className="border rounded p-2 bg-background" value={location} onChange={e=>filter('yieldLocation',e.target.value)}><option value="">All assigned locations</option>{locations?.data.filter(l=>l.active).map(l=><option key={l.id} value={l.id}>{l.name}</option>)}</select></label>
  {user?.role==='pharmacist'&&<label className="flex gap-2"><input type="checkbox" checked={reviewable} onChange={e=>filter('yieldReviewable',e.target.checked?'1':'0')}/>Only proposals I can independently review</label>}
  {locationError&&<p role="alert">Location names could not be loaded. Results remain limited to assigned locations.</p>}
  {isError?<p role="alert">Could not load yield correction reviews. <Button onClick={()=>refetch()}>Retry yield correction reviews</Button></p>:!currentData?<p>Loading yield correction reviews…</p>:<>
   <p>{currentData.data.total} pending proposals{isFetching?' · Refreshing…':''}</p>
   {currentData.data.data.map(p=><article key={p.id} className="border-t py-3"><Link className="underline font-medium" href={worksheet(p.batch_id)}>Yield correction {p.id} · {p.batch_number}</Link><p>{locations?.data.find(l=>l.id===p.location_id)?.name||`Location ${p.location_id}`} · Submitted {p.created_at} UTC</p></article>)}
   {!currentData.data.total&&<p>No pending proposals match these filters.</p>}
   <div className="flex gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>filter('yieldPage',String(page-1))}>Previous yield correction reviews</Button><span>Page {page} / {currentData.data.last_page}</span><Button variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>filter('yieldPage',String(page+1))}>Next yield correction reviews</Button></div>
  </>}
 </section>
}
