"use client"
import {FormEvent, useRef, useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {CompoundBatch, YieldCorrectionProposal, useGetYieldCorrectionsQuery, usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Choice, Field, errorMessage} from './fields'

export function YieldCorrections({batch}:{batch:CompoundBatch}) {
 const [page,setPage]=useState(1)
 const request=useRef<{body:string;id:string}|null>(null)
 const [error,setError]=useState('')
 const {user}=useAuth()
 const execution=batch.execution
 const {currentData,isFetching,isError,refetch}=useGetYieldCorrectionsQuery({id:execution?.id||0,page},{skip:!execution})
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 if(!execution)return null
 const pharmacist=user?.role==='pharmacist'
 const independent=pharmacist&&Number(user?.id)!==execution.created_by&&!execution.addenda.some(a=>a.created_by===Number(user?.id))
 async function submit(event:FormEvent<HTMLFormElement>){
  event.preventDefault();setError('')
  const form=event.currentTarget
  const body={...Object.fromEntries(new FormData(form)),unit:execution!.record.yield_unit,version:currentData!.execution_version}
  const serialized=JSON.stringify(body)
  if(request.current?.body!==serialized)request.current={body:serialized,id:crypto.randomUUID()}
  const id=request.current.id
  try{
   await save({path:`executions/${execution!.id}/yield-corrections`,body:{...body,request_id:id}}).unwrap()
   form.reset();request.current=null;setPage(1)
  }catch(e){setError(errorMessage(e))}
 }
 return <section className="space-y-3 border-t pt-4">
  <h4 className="font-semibold">Yield corrections</h4>
  <p className="text-sm">Correct a documented output quantity using measured evidence. The original record and prior disposal remain retained. Independent review keeps output quarantined and does not change ingredient stock or authorize dispensing.</p>
  {isError?<div role="alert">Correction history could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry correction history</Button></div>:!currentData?<p>Loading correction history…</p>:<>
   <p className="font-medium">Original recorded output: {execution.record.yield_quantity} {execution.record.yield_unit} · Accounted output: {currentData.balance.recorded_yield} {currentData.balance.unit} · Previously disposed: {currentData.balance.previously_disposed} · Still held: {currentData.balance.held_output}</p>
   {currentData.data.data.length===0&&<p>No yield correction proposals recorded.</p>}
   {currentData.data.data.map(p=><article key={p.id} className="border rounded p-3 space-y-2">
    <h5 className="font-medium">Proposal {p.id} · {p.status.replaceAll('_',' ')}</h5>
    <p>Original: {p.proposal.original_yield}. Previous accounted yield: {p.proposal.previous_accounted_yield}. Proposed corrected yield: {p.proposal.corrected_yield} {p.proposal.unit}. Previously disposed: {p.proposal.previously_disposed}. Observed still held: {p.proposal.corrected_held_output}.</p>
    <p className="whitespace-pre-wrap break-words">Reason: {p.correction_evidence.reason}</p>
    <p className="whitespace-pre-wrap break-words">Measurement evidence: {p.correction_evidence.measurement_evidence}</p>
    <p className="whitespace-pre-wrap break-words">Original-source evidence: {p.correction_evidence.source_evidence}</p>
    {p.review_evidence&&<p className="whitespace-pre-wrap break-words">Review: {p.review_evidence}</p>}
    {p.status==='pending'&&(independent&&Number(user?.id)!==p.created_by?<YieldDecision proposal={p} disabled={isFetching}/>:<p>A pharmacist who authored neither this proposal, the execution nor its addenda must review.</p>)}
   </article>)}
   <div className="flex gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous correction page</Button><span>Page {page} of {currentData.data.last_page}</span><Button variant="outline" disabled={page>=currentData.data.last_page||isFetching} onClick={()=>setPage(page+1)}>Next correction page</Button></div>
   {currentData.custody_pending&&<p>Resolve the pending output custody proposal before proposing a yield correction.</p>}
   {pharmacist&&!currentData.pending&&!currentData.custody_pending&&<form onSubmit={submit} className="space-y-3">
    <h5 className="font-medium">Propose a yield correction</h5>
    <p>Use {execution.record.yield_unit}. Corrected total must equal observed held output plus all previously disposed output.</p>
    <Field name="corrected_yield" label="Corrected total output" type="number" min="0" max="999999.999" step="0.001"/>
    <Field name="observed_held" label="Observed output still held" type="number" min="0" max="999999.999" step="0.001"/>
    <Field name="reason" label="Correction reason" maxLength={5000}/>
    <Field name="measurement_evidence" label="Measurement or recount evidence" maxLength={5000}/>
    <Field name="source_evidence" label="Original-source evidence" maxLength={5000}/>
    <p>Saving retains a proposal. An independent pharmacist must review before the accounted balance changes.</p>
    {error&&<p role="alert">{error}</p>}<Button disabled={isLoading||isFetching}>Save correction proposal</Button>
   </form>}
  </>}
 </section>
}

function YieldDecision({proposal,disabled}:{proposal:YieldCorrectionProposal;disabled:boolean}){
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('')
 async function submit(event:FormEvent<HTMLFormElement>){
  event.preventDefault();setError('')
  try{await save({path:`yield-corrections/${proposal.id}/decision`,body:Object.fromEntries(new FormData(event.currentTarget))}).unwrap()}
  catch(e){setError(errorMessage(e))}
 }
 return <form onSubmit={submit} className="space-y-3">
  <Choice name="decision" label="Independent yield correction decision" options={['rejected','applied']}/>
  <Field name="evidence" label="Independent findings and evidence" maxLength={5000}/>
  {error&&<p role="alert">{error}</p>}<Button disabled={disabled||isLoading}>Save correction decision</Button>
 </form>
}
