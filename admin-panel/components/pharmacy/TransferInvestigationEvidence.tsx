export function TransferInvestigationEvidence({details, unit}: {details: string; unit: string}) {
  let record: Record<string, unknown>
  try {
    const parsed: unknown = JSON.parse(details)
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return <p>Investigation details are unavailable. Review the retained evidence below.</p>
    record = parsed as Record<string, unknown>
  } catch {
    return <p>Investigation details are unavailable. Review the retained evidence below.</p>
  }
  const value = (key: string) => typeof record[key] === 'string' || typeof record[key] === 'number' ? String(record[key]) : 'Not recorded'
  return <div className="space-y-2 text-sm">
    <p className="font-semibold">Investigation note · no resolution recorded by this note</p>
    <p className="whitespace-pre-wrap">{value('evidence')}</p>
    <dl className="grid gap-2 sm:grid-cols-2">
      <div><dt className="font-medium">Follow-up owner</dt><dd>{value('follow_up_owner')}</dd></div>
      <div><dt className="font-medium">Follow-up date</dt><dd>{value('follow_up_on')}</dd></div>
      <div className="sm:col-span-2"><dt className="font-medium">Next action</dt><dd className="whitespace-pre-wrap">{value('next_action')}</dd></div>
    </dl>
    <p>At recording: dispatched {value('dispatched_quantity')}, originally received {value('original_received_quantity')}, destination balance {value('destination_quantity_at_recording')} {unit}.</p>
    <p>No stock movement, release, reporting or notification was performed by this note.</p>
  </div>
}
