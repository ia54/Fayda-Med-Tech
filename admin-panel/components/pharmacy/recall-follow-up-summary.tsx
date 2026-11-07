'use client'
import type {RecallNotice} from '@/store/api/pharmacyApiSlice'
import {Button} from '@/components/ui/button'

export function RecallFollowUpSummary({summary,refreshing,select}:{summary:RecallNotice['follow_up_summary'];refreshing:boolean;select:(status:string)=>void}){
 return <section aria-label="Recall follow-up overview" aria-busy={refreshing} className="rounded border p-4 space-y-3">
 <h2 className="text-xl font-semibold">Follow-up across your assigned locations</h2>
 <p>Counts include every matching fill at your current locations, across all pages and follow-up filters. Completed follow-up does not terminate the recall or release stock.</p>
 {refreshing&&<p role="status">Refreshing recall counts…</p>}
 <dl className="grid grid-cols-2 md:grid-cols-4 gap-4">
 <div><dt>Affected fills</dt><dd className="text-2xl font-semibold">{summary.affected_fills}</dd></div>
 <div><dt>Unfinished follow-up</dt><dd className="text-2xl font-semibold">{summary.unfinished}</dd></div>
 <div><dt>Completed follow-up</dt><dd className="text-2xl font-semibold">{summary.completed}</dd></div>
 <div><dt>Unresolved fills</dt><dd className="text-2xl font-semibold">{summary.unresolved_fills}</dd></div>
 </dl>
 <p className="text-sm">Unresolved fills still need their fulfillment or cancellation reviewed; this count can overlap unfinished follow-up. No patient communication is sent by this screen.</p>
 <div className="flex flex-wrap gap-2"><Button variant="outline" disabled={refreshing} onClick={()=>select('not_started')}>Not started ({summary.not_started})</Button><Button variant="outline" disabled={refreshing} onClick={()=>select('open')}>Open follow-up ({summary.open})</Button><Button variant="outline" disabled={refreshing} onClick={()=>select('completed')}>Completed ({summary.completed})</Button><Button variant="outline" disabled={refreshing} onClick={()=>select('')}>All affected fills</Button></div>
 </section>
}
