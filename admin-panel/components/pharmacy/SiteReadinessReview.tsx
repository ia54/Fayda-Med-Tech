'use client'
import {useRef,useState} from 'react'
import {useGetSiteReadinessQuery} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {Dialog,DialogContent,DialogHeader,DialogTitle,DialogDescription} from '@/components/ui/dialog'
export function SiteReadinessReview({locationId}:{locationId:number|null}){
 const trigger=useRef<HTMLButtonElement>(null)
 const [selected,setSelected]=useState<number|null>(null)
 const {currentData,isFetching,isError,refetch}=useGetSiteReadinessQuery(selected!,{skip:selected===null})
 const data=currentData?.data
 return <><Button ref={trigger} aria-haspopup="dialog" aria-expanded={selected!==null} variant="outline" disabled={!locationId} title={locationId?'Review recorded site evidence':'Choose a pharmacy location first'} onClick={()=>setSelected(locationId)}>Site readiness</Button>
 <Dialog open={selected!==null} onOpenChange={open=>{if(!open)setSelected(null)}}><DialogContent data-site-readiness="true" onCloseAutoFocus={event=>{event.preventDefault();trigger.current?.focus()}} className="max-h-[85vh] overflow-y-auto"><DialogHeader><DialogTitle className="pr-6">Site readiness review</DialogTitle><DialogDescription>Recorded evidence and outstanding decisions. This review does not enable clinical release.</DialogDescription></DialogHeader>
 {isFetching?<p role="status">Loading site evidence…</p>:isError?<p role="alert">Site evidence could not be loaded. <Button onClick={()=>refetch()}>Retry</Button></p>:data?<div className="space-y-4"><h2 className="font-semibold">{data.site.name}</h2><p>Site active: {data.site.active?'Yes':'No'}. Operational release: disabled.</p><dl><dt>Facility license reference</dt><dd>{data.recorded.facility_license_reference_present?'Recorded text only — not verified':'Not recorded'}</dd><dt>Current pharmacist assignments</dt><dd>{data.recorded.active_pharmacist_assignments}</dd><dt>Current technician assignments</dt><dd>{data.recorded.active_technician_assignments}</dd></dl><p className="text-sm">{data.basis}</p><ul className="space-y-2">{data.requirements.map(row=><li key={row.id}>{row.label}: <strong>{row.status.replaceAll('_',' ')}</strong></li>)}</ul><ul>{data.workflows.map(row=><li key={row.kind}>{row.kind.replaceAll('_',' ')}: release disabled</li>)}</ul></div>:<p role="alert">Site evidence is unavailable.</p>}
 </DialogContent></Dialog></>
}
