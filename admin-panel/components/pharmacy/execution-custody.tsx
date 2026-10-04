"use client"
import {FormEvent, useRef, useState} from 'react'
import {useAuth} from '@/hooks/useAuth'
import {Button} from '@/components/ui/button'
import {CompoundBatch, ExecutionCustodyProposal, useGetExecutionCustodyQuery, usePharmacyStockActionMutation} from '@/store/api/pharmacyApiSlice'
import {Choice, Field, errorMessage} from './fields'

export function ExecutionCustody({batch}:{batch:CompoundBatch}) {
 const [page,setPage]=useState(1)
 const request=useRef<{body:string;id:string}|null>(null)
 const [error,setError]=useState('')
 const {user}=useAuth()
 const execution=batch.execution
 const {currentData,isFetching,isError,refetch}=useGetExecutionCustodyQuery({id:execution?.id||0,page},{skip:!execution})
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
   await save({path:`executions/${execution!.id}/custody`,body:{...body,request_id:id}}).unwrap()
   form.reset();request.current=null;setPage(1)
  }catch(e){setError(errorMessage(e))}
 }
 return <section className="space-y-3 border-t pt-4">
  <h4 className="font-semibold">Output custody and disposition</h4>
  <p className="text-sm">Account for the output still held from this preparation. Previous disposal is retained separately. These decisions keep output quarantined and do not return ingredients to stock or authorize dispensing.</p>
  {isError?<div role="alert">Custody history could not be loaded. <Button variant="outline" onClick={()=>refetch()}>Retry custody history</Button></div>:!currentData?<p>Loading custody history…</p>:<>
   <p className="font-medium">Original recorded output: {execution.record.yield_quantity} {execution.record.yield_unit} · Accounted output: {currentData.balance.recorded_yield} {currentData.balance.unit} · Previously disposed: {currentData.balance.previously_disposed} · Still held: {currentData.balance.held_output}</p>
   {currentData.data.data.length===0&&<p>No output custody proposals recorded.</p>}
   {currentData.data.data.map(p=><article key={p.id} className="border rounded p-3 space-y-2">
    <h5 className="font-medium">Proposal {p.id} · {p.status.replaceAll('_',' ')}</h5>
    <p>Previously disposed: {p.proposal.previously_disposed} {p.proposal.unit}. This proposal: retain {p.proposal.retained_quarantined}, dispose {p.proposal.disposed_output}, unaccounted {p.proposal.unaccounted_output} {p.proposal.unit}.</p>
    <p className="whitespace-pre-wrap break-words">Evidence: {p.evidence}</p>
    {p.review_evidence&&<p className="whitespace-pre-wrap break-words">Review: {p.review_evidence}</p>}
    {p.container_record_id&&<p>Review the individual findings in container custody. This is the linked aggregate accounting record.</p>}
    {p.status==='pending'&&!p.container_record_id&&(independent&&Number(user?.id)!==p.created_by?<CustodyDecision proposal={p} disabled={isFetching}/>:<p>A pharmacist who authored neither this proposal, the execution nor its addenda must review.</p>)}
   </article>)}
   <div className="flex gap-3 items-center"><Button variant="outline" disabled={page===1||isFetching} onClick={()=>setPage(page-1)}>Previous custody page</Button><span>Page {page} of {currentData.data.last_page}</span><Button variant="outline" disabled={page>=currentData.data.last_page||isFetching} onClick={()=>setPage(page+1)}>Next custody page</Button></div>
   {currentData.yield_pending&&<p>Resolve the pending yield correction before proposing further output custody.</p>}
   {currentData.container_established&&<p>Container custody is established. Record further findings through the individual containers.</p>}
   {pharmacist&&!currentData.container_established&&!currentData.pending&&!currentData.yield_pending&&<form onSubmit={submit} className="space-y-3">
    <h5 className="font-medium">Propose output custody</h5>
    <p>Enter each measured quantity explicitly in {execution.record.yield_unit}; do not include previously disposed output again.</p>
    <Field name="retained_quarantined" label="Output retained in quarantine" type="number" min="0" max="999999.999" step="0.001"/>
    <Field name="disposed_output" label="Output disposed in this proposal" type="number" min="0" max="999999.999" step="0.001"/>
    <Field name="unaccounted_output" label="Output still unaccounted for" type="number" min="0" max="999999.999" step="0.001"/>
    <Field name="evidence" label="Findings, custody and disposition evidence" maxLength={5000}/>
    <p>Unaccounted output prevents application. Saving a proposal does not apply disposal.</p>
    {error&&<p role="alert">{error}</p>}<Button disabled={isLoading||isFetching}>Save custody proposal</Button>
   </form>}
  </>}
 </section>
}

function CustodyDecision({proposal,disabled}:{proposal:ExecutionCustodyProposal;disabled:boolean}){
 const [save,{isLoading}]=usePharmacyStockActionMutation()
 const [error,setError]=useState('')
 async function submit(event:FormEvent<HTMLFormElement>){
  event.preventDefault();setError('')
  try{await save({path:`execution-custody/${proposal.id}/decision`,body:Object.fromEntries(new FormData(event.currentTarget))}).unwrap()}
  catch(e){setError(errorMessage(e))}
 }
 return <form onSubmit={submit} className="space-y-3">
  <Choice name="decision" label="Independent custody decision" options={proposal.proposal.accounting_complete?['rejected','applied']:['rejected']}/>
  <Field name="evidence" label="Independent findings and evidence" maxLength={5000}/>
  {error&&<p role="alert">{error}</p>}<Button disabled={disabled||isLoading}>Save custody decision</Button>
 </form>
}
