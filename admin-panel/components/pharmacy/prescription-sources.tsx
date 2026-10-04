'use client'
import {useEffect,useState} from 'react'
import {useSelector} from 'react-redux'
import {RootState} from '@/store/store'
import {useGetPrescriptionSourcesQuery,useAddPrescriptionSourceMutation} from '@/store/api/pharmacyApiSlice'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'
import {Button} from '@/components/ui/button'
import {Field,errorMessage} from './fields'
import {ExtractionDrafts} from './extraction-drafts'
import {SourceTranscriptions} from './source-transcriptions'

export function PrescriptionSources({id}:{id:number}){
 const {currentData,isFetching,isError,refetch}=useGetPrescriptionSourcesQuery(id)
 const [upload,{isLoading}]=useAddPrescriptionSourceMutation()
 const [error,setError]=useState('');const [success,setSuccess]=useState('');const [requestId,setRequestId]=useState(()=>crypto.randomUUID())
 const [selected,setSelected]=useState<number|null>(null)
 const token=useSelector((s:RootState)=>s.auth.token?.access_token)
 const [download,setDownload]=useState<{url:string;token:string;id:number}|null>(null)
 const [fileError,setFileError]=useState('')
 useEffect(()=>{
  setDownload(null);setFileError('');if(selected===null||!token)return
  const controller=new AbortController();let url=''
  const base=process.env.NEXT_PUBLIC_API_BASE_URL||'http://localhost:8000/api'
  fetch(`${base.replace(/\/$/,'')}/pharmacy/prescriptions/${id}/sources/${selected}/file`,{headers:{Authorization:`Bearer ${token}`},cache:'no-store',signal:controller.signal})
   .then(async response=>{if(!response.ok)throw new Error('The file could not be retrieved. Your access may have changed, or the original file may be unavailable.');return response.blob()})
   .then(blob=>{if(!controller.signal.aborted){url=URL.createObjectURL(blob);setDownload({url,token,id:selected})}})
   .catch(e=>{if(!controller.signal.aborted)setFileError(e.message)})
  return()=>{controller.abort();if(url)URL.revokeObjectURL(url)}
 },[id,selected,token])
 return <Card><CardHeader><CardTitle>Original prescription evidence</CardTitle></CardHeader><CardContent className="space-y-4">
 <p className="text-sm">Retain the received prescription and supporting corrections. Files are kept as separate, permanent records; uploading does not validate a prescription or authorize dispensing. New evidence requires a fresh pharmacist review before preparation or handover; a prepared fill must be cancelled and started again. Synthetic files only in this preview.</p>
 {isError?<p role="alert">Could not load prescription files. <Button variant="outline" onClick={()=>refetch()}>Retry</Button></p>:!currentData?<p role="status">Loading source records…</p>:<ul className="space-y-3">{!currentData?.data.length&&<li>No original prescription file has been retained yet.</li>}{currentData?.data.map(source=><li key={source.id} className="border rounded p-3 space-y-2 break-words">
 <p className="font-medium">{source.original_name}</p><p className="text-sm">{source.reference}</p><p className="text-xs">Staff #{source.created_by} · {source.created_at} · {(source.size/1024).toFixed(1)} KB</p>
 <details><summary className="text-sm underline cursor-pointer">File integrity reference</summary><code className="text-xs break-all">SHA-256: {source.sha256}</code></details>
 <Button variant="outline" onClick={()=>{setSelected(selected===source.id?null:source.id)}}>{selected===source.id?'Close file access':'Retrieve original file'}</Button>
 {selected===source.id&&(fileError?<p role="alert">{fileError}</p>:download?.id===source.id&&download.token===token?<a className="block underline" href={download.url} download={source.original_name}>Download {source.original_name}</a>:<p role="status">Checking access and file integrity…</p>)}
 <SourceTranscriptions id={id} source={source} disabled={isFetching}/>
 </li>)}</ul>}
 <form className="grid gap-3" onSubmit={async e=>{e.preventDefault();const form=e.currentTarget;const body=new FormData(form);body.set('request_id',requestId);setError('');setSuccess('');try{await upload({id,body}).unwrap();form.reset();setRequestId(crypto.randomUUID());setSuccess('Original evidence retained. Existing files have not been replaced.')}catch(e){setError(errorMessage(e))}}}>
 <Field name="reference" label="Source / receipt reference and reason for attaching" maxLength={500}/>
 <label className="grid gap-1 text-sm">Prescription file (PDF, PNG or JPEG; up to 10 MB)<input className="w-full min-w-0" type="file" name="file" accept="application/pdf,image/png,image/jpeg" required onChange={()=>setRequestId(crypto.randomUUID())}/></label>
 <Button disabled={isLoading}>{isLoading?'Retaining source file…':'Retain original evidence'}</Button>
 {error&&<p role="alert">{error}</p>}{success&&<p role="status">{success}</p>}
 </form><ExtractionDrafts id={id}/></CardContent></Card>
}
