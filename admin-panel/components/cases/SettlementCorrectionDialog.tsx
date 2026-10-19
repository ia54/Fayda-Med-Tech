"use client"
import { useRef, useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { useCorrectSettlementMutation } from "@/store/api/apiSlice"

interface Record {
  id: number; settlement_amount: string; settlement_date: string; notes: string;
  attorney_fees?: string | null; costs?: string | null; other_deductions?: string | null;
}
export function SettlementCorrectionDialog({ record, onClose }: { record: Record; onClose: () => void }) {
  const [correct, { isLoading }] = useCorrectSettlementMutation()
  const [error, setError] = useState("")
  const [form, setForm] = useState({
    settlement_amount: record.settlement_amount, settlement_date: record.settlement_date.slice(0, 10),
    attorney_fees: record.attorney_fees || "", costs: record.costs || "", other_deductions: record.other_deductions || "",
    notes: record.notes || "", correction_reason: "",
  })
  const attempt = useRef<{ body: string; key: string } | null>(null)
  const saving = useRef(false)
  const fields = [
    ["settlement_amount", "Corrected gross amount ($)"], ["attorney_fees", "Attorney fees ($)"],
    ["costs", "Costs ($)"], ["other_deductions", "Other deductions ($)"],
  ] as const
  const save = async () => {
    if (saving.current) return
    setError("")
    if (!form.correction_reason.trim() || !form.settlement_date || fields.some(([key]) => !/^\d+(\.\d{1,2})?$/.test(form[key]))) {
      setError("Enter the correction reason, date and all amounts with at most two decimal places. Confirm unknown deductions before proceeding.")
      return
    }
    saving.current = true
    try {
      const body = JSON.stringify(form)
      if (attempt.current?.body !== body) attempt.current = { body, key: crypto.randomUUID() }
      await correct({ id: record.id, ...form, request_id: attempt.current.key }).unwrap()
      onClose()
    } catch (err: any) {
      setError(Object.values(err.data?.errors || {}).flat().join(" ") || err.data?.message || "Could not record the correction. Try again with these details.")
    } finally { saving.current = false }
  }
  return <Dialog open onOpenChange={open => { if (!open && !saving.current) onClose() }}>
    <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>Correct completed settlement #{record.id}</DialogTitle>
        <DialogDescription>This creates a completed replacement and preserves the original ${record.settlement_amount} record. Reports and case values will use the replacement. No payment is made or reversed.</DialogDescription>
      </DialogHeader>
      {error && <p role="alert" className="text-destructive">{error}</p>}
      <fieldset disabled={isLoading} className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {fields.map(([key, label]) => <div key={key}><Label htmlFor={`correct-${key}`}>{label}</Label><Input id={`correct-${key}`} type="number" min="0" step="0.01" value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} /></div>)}
        </div>
        <div><Label htmlFor="correct-date">Corrected settlement date</Label><Input id="correct-date" type="date" value={form.settlement_date} onChange={e => setForm({ ...form, settlement_date: e.target.value })} /></div>
        <div><Label htmlFor="correct-reason">Reason for correction</Label><Textarea id="correct-reason" maxLength={2000} value={form.correction_reason} onChange={e => setForm({ ...form, correction_reason: e.target.value })} /></div>
        <div><Label htmlFor="correct-notes">Replacement notes</Label><Textarea id="correct-notes" value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} /></div>
      </fieldset>
      <div className="flex justify-end gap-3"><Button variant="outline" disabled={isLoading} onClick={onClose}>Cancel</Button><Button disabled={isLoading} onClick={save}>{isLoading ? "Saving…" : "Record Correction"}</Button></div>
    </DialogContent>
  </Dialog>
}
