"use client"
import { useState } from "react"
import { useGetInsuranceCorrespondenceQuery, useCreateInsuranceCorrespondenceMutation } from "@/store/api/apiSlice"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"

export function InsuranceCorrespondence({ claimId }: { claimId: number }) {
  const [page, setPage] = useState(1)
  const [note, setNote] = useState("")
  const [channel, setChannel] = useState("phone")
  const [date, setDate] = useState("")
  const [requestId, setRequestId] = useState("")
  const [error, setError] = useState("")
  const [success, setSuccess] = useState(false)
  const { currentData: response, isFetching, isError, refetch } = useGetInsuranceCorrespondenceQuery({ claim_id: claimId, page, per_page: 10 })
  const [save, { isLoading }] = useCreateInsuranceCorrespondenceMutation()
  const changed = () => { setRequestId(""); setSuccess(false) }
  async function submit(e: React.FormEvent) {
    e.preventDefault()
    if (isLoading || success) return
    const key = requestId || crypto.randomUUID()
    setRequestId(key); setError("")
    try {
      await save({ claim_id: claimId, request_id: key, channel, occurred_on: date, note: note.trim() }).unwrap()
      setSuccess(true); setPage(1)
    } catch (e: any) {
      setError(Object.values(e?.data?.errors || {}).flat().join(" ") || e?.data?.message || "Could not save the note. Retry without changing it to avoid duplicate entries.")
    }
  }
  return <section className="space-y-3 border-t pt-3">
    <h4 className="font-semibold">Correspondence log</h4>
    <p className="text-sm text-muted-foreground">Record a past contact. Saving here does not send a message.</p>
    {isError ? <div role="alert">Could not load correspondence. <Button variant="outline" onClick={() => refetch()}>Try again</Button></div> : isFetching ? <p>Loading notes…</p> : <div className="space-y-2">{response?.data?.data?.length ? response.data.data.map((entry: any, i: number) => <article key={entry?.id || i} className="border rounded p-2"><p className="text-sm">{typeof entry?.occurred_on === "string" ? entry.occurred_on : "Date not recorded"} · {typeof entry?.channel === "string" ? entry.channel : "Legacy entry"}</p><p className="whitespace-pre-wrap break-words">{typeof entry?.note === "string" ? entry.note : "Legacy entry retained; review the original record."}</p>{typeof entry?.recorded_at === "string" && <p className="text-xs text-muted-foreground">Recorded {entry.recorded_at}</p>}</article>) : <p>No correspondence recorded.</p>}</div>}
    <div className="flex justify-between gap-2"><Button size="sm" variant="outline" disabled={page<=1 || isFetching} onClick={()=>setPage(p=>p-1)}>Previous page</Button><span>{page} / {response?.data?.last_page || 1}</span><Button size="sm" variant="outline" disabled={isFetching || page >= (response?.data?.last_page || 1)} onClick={()=>setPage(p=>p+1)}>Next page</Button></div>
    {error && <p role="alert" className="text-destructive">{error}</p>}
    {success ? <div role="status">Note saved. <Button variant="outline" onClick={()=>{ setNote(""); setDate(""); changed() }}>Record another contact</Button></div> : <form onSubmit={submit}><fieldset disabled={isLoading} className="space-y-2">
      <label className="block">Contact date<Input type="date" required value={date} onChange={e=>{setDate(e.target.value);changed()}} /></label>
      <label className="block">Contact method<select className="block w-full rounded border p-2 bg-background" value={channel} onChange={e=>{setChannel(e.target.value);changed()}}>{["phone","email","letter","meeting","other"].map(x=><option key={x} value={x}>{x}</option>)}</select></label>
      <label className="block">Note<textarea className="block w-full rounded border p-2 bg-background" required maxLength={10000} value={note} onChange={e=>{setNote(e.target.value);changed()}} /></label>
      <Button type="submit" disabled={isLoading || !note.trim() || !date}>{isLoading ? "Saving…" : "Record contact"}</Button>
    </fieldset></form>}
  </section>
}
