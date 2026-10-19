'use client'
import {useState} from 'react'
import {PrescriptionSource,useGetSourceTranscriptionsQuery,useRetainSourceTranscriptionMutation} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'
import {errorMessage} from './fields'

export function SourceTranscriptions({id,source,disabled}:{id:number;source:PrescriptionSource;disabled:boolean}){
 const [open,setOpen]=useState(false)
 return <div className="border-t pt-3"><Button variant="outline" className="h-auto whitespace-normal" onClick={()=>setOpen(!open)}>{open?'Close source text':'Source text and transcription history'}</Button>{open&&<TranscriptionPanel id={id} source={source} disabled={disabled}/>}</div>
}
function TranscriptionPanel({id,source,disabled}:{id:number;source:PrescriptionSource;disabled:boolean}){
 const {currentData,isFetching,isError,refetch}=useGetSourceTranscriptionsQuery({id,sourceId:source.id})
 if(isError)return <p role="alert">Source text could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>
 if(!currentData)return <p role="status">Loading retained source text…</p>
 const latest=currentData.data.data[0]
 return <div className="space-y-4 mt-3"><p className="text-sm">Manual transcription only. OCR and AI are not connected. Copy the original page text exactly, keeping blank or unreadable pages in their original position. This text remains unverified and cannot change a prescription or approve dispensing. Synthetic records only.</p>
 <TranscriptionForm key={`${source.id}-${latest?.id??'new'}`} id={id} source={source} previousId={latest?.id??null} previousPages={latest?.pages} disabled={disabled||isFetching}/>
 <TranscriptionHistory id={id} sourceId={source.id}/></div>
}
function TranscriptionForm({id,source,previousId,previousPages,disabled}:{id:number;source:PrescriptionSource;previousId:number|null;previousPages?:string[];disabled:boolean}){
 const [pages,setPages]=useState(previousPages??['']),[reference,setReference]=useState(''),[confirmed,setConfirmed]=useState(false),[error,setError]=useState(''),[requestId]=useState(()=>crypto.randomUUID())
 const [save,{isLoading}]=useRetainSourceTranscriptionMutation();const busy=disabled||isLoading
 return <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();setError('');try{await save({id,sourceId:source.id,body:{request_id:requestId,previous_id:previousId,source_sha256:source.sha256,transcription_pages:pages,reference,confirmed}}).unwrap()}catch(e){setError(errorMessage(e))}}}>
 <p className="font-medium">{previousId?`Retain a correction to transcription #${previousId}`:'Retain manual page text'}</p>
 {pages.map((text,index)=><label key={index} className="grid gap-1 text-sm">Page {index+1} text<textarea className="rounded border p-2 bg-background min-h-28 w-full" value={text} disabled={busy} maxLength={250000} onChange={e=>{setPages(pages.map((p,n)=>n===index?e.target.value:p));setConfirmed(false)}}/></label>)}
 <div className="flex flex-wrap gap-2"><Button type="button" variant="outline" disabled={busy||pages.length>=100} onClick={()=>{setPages([...pages,'']);setConfirmed(false)}}>Add next page</Button><Button type="button" variant="outline" disabled={busy||pages.length<=1} onClick={()=>{setPages(pages.slice(0,-1));setConfirmed(false)}}>Remove last unsaved page</Button></div>
 <label className="grid gap-1 text-sm">Transcription source and correction reason<textarea className="rounded border p-2 bg-background" required maxLength={2000} value={reference} disabled={busy} onChange={e=>{setReference(e.target.value);setConfirmed(false)}}/></label>
 <label className="flex gap-2 text-sm items-start"><input type="checkbox" required checked={confirmed} disabled={busy} onChange={e=>setConfirmed(e.target.checked)}/>I compared the page order and text with this original file. This is a manual, unverified transcription; earlier text remains in history.</label>
 <Button disabled={busy} className="h-auto whitespace-normal">Retain transcription without changing prescription</Button>{error&&<p role="alert">{error}</p>}
 </form>
}
function TranscriptionHistory({id,sourceId}:{id:number;sourceId:number}){
 const [page,setPage]=useState(1);const {currentData,isFetching,isError,refetch}=useGetSourceTranscriptionsQuery({id,sourceId,page})
 if(isError)return <p role="alert">Transcription history could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry history</Button></p>
 return <div className="space-y-3"><h3 className="font-semibold">Retained transcription history</h3>{!currentData?<p role="status">Loading history…</p>:<>{currentData.data.total===0&&<p>No transcription retained.</p>}{currentData.data.data.map(t=><details key={t.id} className="rounded border p-3"><summary className="cursor-pointer">Transcription #{t.id} · Manual, unverified · Staff #{t.created_by}</summary><div className="space-y-2 mt-2 break-words"><p>{t.created_at} · {t.supersedes_id?`Corrects transcription #${t.supersedes_id}; earlier record retained`:'Initial transcription'}</p><p>{t.reference}</p>{t.pages.map((text,n)=><div key={n}><h4 className="font-medium">Page {n+1}</h4><p className="whitespace-pre-wrap">{text||'No readable text recorded for this page.'}</p></div>)}</div></details>)}<div className="flex flex-wrap gap-2 items-center"><Button variant="outline" disabled={isFetching||page<=1} onClick={()=>setPage(page-1)}>Previous transcriptions</Button><span>Page {page} of {currentData.data.last_page}</span><Button variant="outline" disabled={isFetching||page>=currentData.data.last_page} onClick={()=>setPage(page+1)}>Next transcriptions</Button></div></>}</div>
}
