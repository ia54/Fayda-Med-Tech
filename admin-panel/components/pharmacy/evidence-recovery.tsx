'use client'
import {useState,type FormEvent} from 'react'
import {Button} from '@/components/ui/button'
import {errorMessage} from './fields'
export type RecoverySummary={id:number;status:string;created_by:number;version?:number}
export function EvidenceRecovery({label,id,summary,role,userId,excludedAuthors=[],busy,requestError,retry,reject}:{label:string;id:number;summary?:RecoverySummary;role:string;userId:number;excludedAuthors?:number[];busy:boolean;requestError:unknown;retry:()=>void;reject:(evidence:string)=>Promise<unknown>}){
 const [saving,setSaving]=useState(false);const [error,setError]=useState('');const [saved,setSaved]=useState(false)
 const conflict=(requestError as {status?:number})?.status===409
 const pending=summary?.id===id&&['pending','draft'].includes(summary.status)
 const independent=Number.isInteger(userId)&&userId>0&&summary?.created_by!==userId&&!excludedAuthors.includes(userId)
 const canReject=conflict&&pending&&role==='pharmacist'&&independent
 async function submit(e:FormEvent<HTMLFormElement>){e.preventDefault();if(!canReject||busy||saving)return;setError('');setSaving(true);try{await reject(String(new FormData(e.currentTarget).get('evidence')));setSaved(true)}catch(e){setError(errorMessage(e))}finally{setSaving(false)}}
 return <section className="border rounded p-4 space-y-3"><h4 className="font-semibold">{label} #{id}: evidence unavailable</h4><p role="alert">{errorMessage(requestError)}</p><Button type="button" variant="outline" disabled={busy||saving} onClick={retry}>Retry {label.toLowerCase()} detail</Button>
 {summary?.id===id&&<p>Retained history status: {summary.status} · Author #{summary.created_by}</p>}
 {saved?<p role="status">Rejection retained. Original evidence remains in history; no medication release is authorized.</p>:canReject?<form onSubmit={submit}><fieldset disabled={busy||saving} className="space-y-3"><p>The pending record failed its evidence check. You may reject it with an investigation reference so a corrected proposal can be recorded. This does not validate, repair or delete the original evidence.</p><label className="grid gap-1">Rejection reason and investigation reference<textarea name="evidence" required maxLength={5000} rows={3} className="border rounded p-2 bg-background"/></label><label className="flex gap-2"><input type="checkbox" required/>I verified the record identity and am rejecting this unusable proposal. I am not approving its contents.</label>{error&&<p role="alert">{error}</p>}<Button>Reject unusable proposal</Button></fieldset></form>:conflict&&pending&&role==='pharmacist'?<p>A pharmacist independent of the proposal and preparation must record any rejection.</p>:null}
 </section>
}
