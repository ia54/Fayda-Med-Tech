"use client"
import Link from 'next/link'
import {StockTransfer} from '@/store/api/pharmacyApiSlice'
import {Field,Choice} from './fields'
import {TransferInvestigationEvidence} from './TransferInvestigationEvidence'
import {Button} from '@/components/ui/button'
import {Card,CardHeader,CardTitle,CardContent} from '@/components/ui/card'

export function TransferResolutions({transfer:t,userId,role,disabled,page,changePage,save}:{transfer:StockTransfer;userId:number;role:string;disabled:boolean;page:number;changePage:(p:number)=>void;save:(path:string,body:Record<string,unknown>)=>void}) {
 return <Card><CardHeader><CardTitle>Resolve an investigated stock variance</CardTitle></CardHeader><CardContent className="space-y-4">
  <p>Synthetic workflow for manufactured, noncontrolled, nonhazardous stock. Controlled medications, compounds and unresolved product identity require their dedicated procedures. A receiving pharmacist proposes the outcome; a different sending-location pharmacist reviews it. Acceptance records custody reconciliation only. Stock stays quarantined for a separate release review.</p>
  <p>A confirmed shortage must match the original short receipt. For a documented excess return, record the completed external return evidence and independently review a physical count from the original excess balance down to the dispatched quantity. This workflow does not authorize a return, submit a loss report or move stock again.</p>
  {t.resolution_pending&&<p role="status">A variance proposal awaits independent review. Existing custody and stock holds remain.</p>}
  {t.status==='received_discrepancy'&&t.can_receive&&role==='pharmacist'&&!t.resolution_pending&&!t.correction_pending&&<>
   <Link className="underline" href={`/dashboard/pharmacy/stock/${t.destination_lot_id}`}>Review destination product and physical-count evidence</Link>
   {t.latest_investigation?<div className="border rounded p-3"><p className="font-semibold">Latest investigation #{t.latest_investigation.id}</p><TransferInvestigationEvidence details={t.latest_investigation.details} unit={t.product.quantity_unit}/></div>:<p>Record investigation findings before proposing resolution.</p>}
   {!!t.correction_candidates.length&&t.latest_investigation?<form key={t.resolutions.total} className="grid gap-3" onSubmit={e=>{e.preventDefault();const b=Object.fromEntries(new FormData(e.currentTarget));save('resolutions',{...b,stock_count_id:Number(b.stock_count_id),investigation_event_id:t.latest_investigation!.id,ordinary_manufactured_stock_confirmed:b.ordinary_manufactured_stock_confirmed==='on'})}}>
    <Choice name="kind" label="Investigated outcome" options={['confirmed_shortage','excess_returned']}/>
    <label className="grid gap-1 text-sm">Independently reviewed physical count<select name="stock_count_id" required defaultValue="" className="border rounded p-2 bg-background"><option value="">Select reviewed count</option>{t.correction_candidates.map(c=><option key={c.id} value={c.id}>Count #{c.id} · {c.counted_quantity} {t.product.quantity_unit} · Reviewer #{c.reviewed_by}</option>)}</select></label>
    <Field name="evidence" label="Investigation outcome and supporting evidence" maxLength={5000}/>
    <Field name="classification_evidence" label="Product classification source and verification evidence" maxLength={5000}/>
    <label className="flex items-start gap-2"><input type="checkbox" name="ordinary_manufactured_stock_confirmed" required/>I verified this stock is manufactured, noncontrolled, noncompounded and nonhazardous.</label>
    <Field name="reporting_assessment" label="Reporting assessment, responsible person and evidence" maxLength={5000}/>
    <Field name="external_return_evidence" label="Completed excess-return evidence, recipient and reference (required for excess returned)" required={false} maxLength={5000}/>
    <Button disabled={disabled}>Propose variance resolution</Button>
   </form>:<p>A current independently reviewed physical count and retained investigation are required.</p>}
  </>}
  {t.resolutions.data.map(r=><section key={r.id} className="border rounded p-4 space-y-2 break-words">
   <h3 className="font-semibold">Resolution #{r.id} · {r.status} · {r.kind.replaceAll('_',' ')}</h3>
   <p>Proposed by pharmacist #{r.created_by} · {r.created_at}</p>
   <p>Dispatched {r.snapshot?.dispatched_quantity??'Not recorded'} · Originally received {r.snapshot?.original_received_quantity??'Not recorded'} · Verified {r.snapshot?.verified_quantity??'Not recorded'} {t.product.quantity_unit}</p>
   <p>Variance {r.snapshot?.variance_quantity??'Not recorded'} {t.product.quantity_unit} · Count #{r.stock_count_id} · Investigation #{r.investigation_event_id}</p>
   <p className="whitespace-pre-wrap">{r.evidence}</p>
   <p className="whitespace-pre-wrap">Classification: {r.classification_evidence}</p>
   <p className="whitespace-pre-wrap">Reporting assessment: {r.reporting_assessment}</p>
   {r.external_return_evidence&&<p className="whitespace-pre-wrap">Completed return: {r.external_return_evidence}</p>}
   {r.reviewed_by&&<p className="whitespace-pre-wrap">Reviewed by pharmacist #{r.reviewed_by} · {r.reviewed_at}<br/>{r.review_evidence}</p>}
   {r.status==='applied'&&<p>Custody resolution recorded. No stock movement was posted by acceptance. Review the destination receipt separately before release.</p>}
   {r.status==='pending'&&role==='pharmacist'&&t.can_dispatch&&(Number(r.created_by)===userId?<p>A different sending-location pharmacist must review your proposal.</p>:<form className="grid gap-3" onSubmit={e=>{e.preventDefault();save(`resolutions/${r.id}/review`,Object.fromEntries(new FormData(e.currentTarget)))}}>
    <Choice name="decision" label={`Decision for resolution #${r.id}`} options={['reject','apply']}/>
    <Field name="evidence" label={`Independent review evidence for resolution #${r.id}`} maxLength={5000}/>
    <label className="flex items-start gap-2"><input type="checkbox" required/>I reviewed the original custody quantities, physical count, product classification, investigation and reporting or return evidence.</label>
    <Button disabled={disabled}>Record variance decision #{r.id}</Button>
   </form>)}
  </section>)}
  {!t.resolutions.data.length&&<p>No variance resolutions recorded.</p>}
  <div className="flex flex-wrap gap-3 items-center"><Button variant="outline" disabled={disabled||page===1} onClick={()=>changePage(page-1)}>Previous resolutions</Button><span>Page {page} of {t.resolutions.last_page} · {t.resolutions.total} records</span><Button variant="outline" disabled={disabled||page>=t.resolutions.last_page} onClick={()=>changePage(page+1)}>Next resolutions</Button></div>
 </CardContent></Card>
}
