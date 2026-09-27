'use client'
import {useEffect,useState} from 'react'
import {useSelector} from 'react-redux'
import {RootState} from '@/store/store'
import {type PharmacyFill,useGetFillLabelsQuery,useGetLabelPrintsQuery,usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'

export function FillLabels({rxId,fill,role,restricted,disabled}:{rxId:number;fill:PharmacyFill;role:string;restricted:boolean;disabled:boolean}){
 const [page,setPage]=useState(1);const [selected,setSelected]=useState<number|null>(null)
 const {currentData,isFetching,isError,refetch}=useGetFillLabelsQuery({rxId,fillId:fill.id,page})
 const data=currentData?.data;const current=data?.current
 const base=`prescriptions/${rxId}/fills/${fill.id}/labels`
 return <section className="border rounded p-3 space-y-3 min-w-0"><h4 className="font-semibold">Dispensing label proofs</h4>
 <p className="text-sm">Synthetic records only. Retain the label proof, inspect it, then record simulated print evidence before the final label check. A download does not establish printing. New proofs include a unique internal Code 128 label. Scan or explicitly compare the codes at preparation and handover. Physical printer/scanner validation and clinical acceptance are still pending.</p>
 {isError?<p role="alert">Could not load labels. <Button variant="outline" onClick={()=>refetch()}>Retry labels</Button></p>:!data?<p role="status">Loading label records…</p>:<>
 {current?<p className="text-sm">Latest label: revision {current.revision} · {current.intact?(current.fresh?'Source details current':'Source details changed — new label required'):'Integrity check failed — investigate'} · {current.print_count} print records · {current.barcode_supported?'Internal code retained':'Historical proof without internal code; retain a new proof before preparation'} · Use by {current.use_by||'unavailable'}</p>:<p className="text-sm">No label retained for this fill. Preparation and handover require the current label and print evidence.</p>}
 {role==='pharmacist'&&!restricted&&fill.fulfillment_status==='pending'&&fill.review_status==='approved'&&<LabelForm key={`${data.fill_version}-${data.source_token}-${current?.id||0}`} path={base} version={data.fill_version} token={data.source_token} previousId={current?.id||0} disabled={disabled||isFetching}/>}
 <ul className="space-y-3">{data.labels.data.map(label=><li key={label.id} className="border rounded p-3 space-y-2 text-sm break-words"><p className="font-medium">Label revision {label.revision} {label.id===current?.id?'— latest retained version':'— historical; do not use for preparation'}</p><p>{label.reason}</p><p>Pharmacist #{label.created_by} · {label.created_at}</p><details><summary className="underline cursor-pointer">Label integrity reference</summary><code className="break-all">SHA-256: {label.sha256}</code></details><Button variant="outline" onClick={()=>setSelected(selected===label.id?null:label.id)}>{selected===label.id?'Close label proof':'View retained label proof'}</Button>{selected===label.id&&<LabelDocument path={`${base}/${label.id}/file`} labelId={label.id}/>}
 <PrintRecords rxId={rxId} fillId={fill.id} labelId={label.id} path={`${base}/${label.id}/prints`} canRecord={!restricted&&label.id===current?.id&&!!current?.fresh&&!!current?.intact&&['pending','ready'].includes(fill.fulfillment_status)&&fill.review_status==='approved'} disabled={disabled||isFetching}/>
 </li>)}</ul><div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous labels</Button><span>Page {page} of {data.labels.last_page} · {data.labels.total} labels</span><Button variant="outline" disabled={page>=data.labels.last_page||isFetching} onClick={()=>setPage(page+1)}>Next labels</Button></div>
 </>}
 </section>
}
function Decision({name,label}:{name:string;label:string}){return <label className="grid gap-1 text-sm">{label}<select name={name} required defaultValue="" className="border rounded p-2 bg-background"><option value="" disabled>Select decision</option><option value="false">No</option><option value="true">Yes</option></select></label>}
function LabelForm({path,version,token,previousId,disabled}:{path:string;version:number;token:string;previousId:number;disabled:boolean}){
 const [requestId]=useState(()=>crypto.randomUUID());const [error,setError]=useState('');const [mutate,{isLoading}]=usePharmacyStockActionMutation()
 return <details><summary className="underline cursor-pointer">{previousId?'Retain corrected label proof':'Create label proof'}</summary><form className="grid gap-3 mt-3 md:grid-cols-2" onSubmit={async e=>{e.preventDefault();setError('');const d=Object.fromEntries(new FormData(e.currentTarget));try{await mutate({path,body:{...d,request_id:requestId,version,source_token:token,previous_label_id:previousId,substituted:d.substituted==='true',daw:d.daw==='true',do_not_label:d.do_not_label==='true',confirmed:d.confirmed==='on'}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <p className="text-sm md:col-span-2">Patient, pharmacy, directions, quantity, prescriber and verified product are captured from the saved records. Corrections retain the older proof. A prepared fill must be cancelled and started again. Do not infer a use-by date or substitution authority from this form.</p>
 <Field name="dispensed_on" label="Dispensing date for the label" type="date"/>
 <Field name="use_by" label="Pharmacist-established use-by date" type="date"/>
 <Field name="use_by_reference" label="Use-by source / packaging basis" maxLength={2000}/>
 <Decision name="daw" label="Prescriber requires dispense as written?"/>
 <Decision name="substituted" label="Product substituted for the prescribed product?"/>
 <Field name="selection_reference" label="Prescription / product-selection authority evidence" maxLength={2000}/>
 <Field name="notification_reference" label="Purchaser notification evidence (required for substitution)" required={false} maxLength={2000}/>
 <Decision name="do_not_label" label="Prescriber explicitly instructed do not label?"/>
 <Field name="disclosure_reference" label="Do-not-label instruction reference (required if yes)" required={false} maxLength={2000}/>
 <label className="grid gap-1 text-sm md:col-span-2">Verified storage / auxiliary label instructions (if applicable)<textarea name="auxiliary_text" maxLength={2000} className="border rounded p-2 bg-background"/></label>
 <Field name="reason" label="Reason for label issue or correction" maxLength={2000}/>
 <label className="flex gap-2 items-start text-sm md:col-span-2"><input name="confirmed" type="checkbox" required/>I checked the source records, product selection, disclosure instructions, dates and auxiliary text. No clinical instructions were inferred by the software.</label>
 {error&&<p role="alert" className="md:col-span-2 text-red-700 dark:text-red-300">{error}</p>}<Button disabled={disabled||isLoading}>{isLoading?'Retaining proof…':'Retain label proof'}</Button>
 </form></details>
}
function LabelDocument({path,labelId}:{path:string;labelId:number}){
 const token=useSelector((s:RootState)=>s.auth.token?.access_token);const [file,setFile]=useState<{url:string;token:string;path:string}|null>(null);const [error,setError]=useState('')
 useEffect(()=>{setFile(null);setError('');if(!token)return;const controller=new AbortController();let url='';const base=process.env.NEXT_PUBLIC_API_BASE_URL||'http://localhost:8000/api';fetch(`${base.replace(/\/$/,'')}/pharmacy/${path}`,{headers:{Authorization:`Bearer ${token}`},cache:'no-store',signal:controller.signal}).then(async r=>{if(!r.ok)throw new Error('Cannot retrieve this label. Access or integrity may have changed.');return r.blob()}).then(blob=>{if(!controller.signal.aborted){url=URL.createObjectURL(blob);setFile({url,token,path})}}).catch(e=>{if(!controller.signal.aborted)setError(e.message)});return()=>{controller.abort();if(url)URL.revokeObjectURL(url)}},[path,token])
 return error?<p role="alert">{error}</p>:file?.token===token&&file?.path===path?<div className="space-y-2"><iframe title={`Synthetic retained label ${labelId}`} src={file.url} sandbox="" className="w-full h-[560px] border bg-white"/><a href={file.url} download={`synthetic-label-${labelId}.html`} className="block underline">Download retained HTML label proof</a><p>Historical proofs remain available for audit. This download does not mark a label printed or authorize dispensing.</p></div>:<p role="status">Checking label access and integrity…</p>
}
function PrintRecords({rxId,fillId,labelId,path,canRecord,disabled}:{rxId:number;fillId:number;labelId:number;path:string;canRecord:boolean;disabled:boolean}){
 const [page,setPage]=useState(1);const [requestId,setRequestId]=useState(()=>crypto.randomUUID());const [error,setError]=useState('');const [success,setSuccess]=useState('');const [mutate,{isLoading}]=usePharmacyStockActionMutation()
 const {currentData,isFetching,isError}=useGetLabelPrintsQuery({rxId,fillId,labelId,page});const prints=currentData?.data
 return <details><summary className="underline cursor-pointer">Print and reprint evidence ({prints?.total??'…'})</summary><div className="space-y-3 mt-3">{isError?<p role="alert">Print history could not be loaded.</p>:prints?.data.map(p=><div className="border rounded p-2" key={p.id}><p>{p.copies} copies · Reported {p.occurred_on} · Staff #{p.created_by}</p><p>{p.reason}</p><p>Evidence: {p.reference}</p><p>Recorded {p.created_at}</p></div>)}{prints&&<div className="flex flex-wrap gap-2 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous prints</Button><span>Page {page} of {prints.last_page}</span><Button variant="outline" disabled={page>=prints.last_page||isFetching} onClick={()=>setPage(page+1)}>Next prints</Button></div>}
 {canRecord&&<form key={requestId} className="grid gap-3 md:grid-cols-2" onSubmit={async e=>{e.preventDefault();setError('');setSuccess('');const body=Object.fromEntries(new FormData(e.currentTarget));try{await mutate({path,body:{...body,copies:Number(body.copies),confirmed:body.confirmed==='on',request_id:requestId}}).unwrap();setRequestId(crypto.randomUUID());setSuccess('Print evidence retained. No printer command was sent.')}catch(e){setError(errorMessage(e))}}}>
 <p className="md:col-span-2">Record simulated print evidence here for synthetic acceptance. Every additional print needs its own reason and evidence. The application does not verify that a printer produced these copies.</p>
 <Field name="copies" label="Number of label copies" type="number" min={1} max={20} step={1} defaultValue={1}/><Field name="occurred_on" label="Print evidence date" type="date"/><Field name="reason" label="Print / reprint reason" maxLength={2000}/><Field name="reference" label="Printer and output-check evidence" maxLength={2000}/><label className="flex gap-2 items-start md:col-span-2"><input name="confirmed" type="checkbox" required/>I checked this exact retained label version and recorded the copies and output evidence accurately for this synthetic test.</label><Button disabled={disabled||isFetching||isLoading}>Record print evidence</Button></form>}{error&&<p role="alert">{error}</p>}{success&&<p role="status">{success}</p>}</div></details>
}
