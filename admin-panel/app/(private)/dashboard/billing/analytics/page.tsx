"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetBillingAnalyticsQuery } from "@/store/api/billingApiSlice"

export default function AnalyticsPage() {
  const { currentData, isFetching, isError, refetch } = useGetBillingAnalyticsQuery()
  const data = currentData?.data
  const rate = (value: number | null | undefined) => value == null ? "Not recorded" : `${value}%`
  return <div className="space-y-6">
    <header><h1 className="text-3xl font-bold text-primary">Recorded billing analytics</h1>
      <p>Organization records and recorded receipts. These figures do not verify insurer outcomes.</p></header>
    {isError ? <p role="alert">Billing analytics are unavailable. <Button onClick={() => refetch()}>Retry</Button></p>
      : isFetching ? <p role="status">Loading analytics…</p>
      : !data ? <p role="alert">Billing analytics are unavailable.</p> : <>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["Invoices marked denied", rate(data.metrics.denial_rate)],
            ["Appeals marked accepted", rate(data.metrics.appeal_success_rate)],
            ["Net recorded receipts this month", Number(data.metrics.revenue_recovery).toLocaleString("en-US", {style:"currency",currency:"USD"})],
            ["Average processing duration", "Unavailable"],
          ].map(([label,value]) => <Card key={label}><CardHeader><CardTitle>{label}</CardTitle></CardHeader><CardContent className="text-2xl font-semibold">{value}</CardContent></Card>)}
        </div>
        <Card><CardHeader><CardTitle>Recorded appeal reasons</CardTitle></CardHeader><CardContent>
          {data.denial_reasons.length ? <ul className="space-y-2">{data.denial_reasons.map((row: {reason:string;count:number;percentage:number}) => <li key={row.reason}>{row.reason || "Not recorded"}: {row.count} ({row.percentage}%)</li>)}</ul> : <p>No appeal reasons recorded.</p>}
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Calculation basis</CardTitle></CardHeader><CardContent className="space-y-2">
          <p>{data.basis.scope}</p><p>{data.basis.rates}</p><p>{data.basis.receipts}</p>
          <p>Receipt dates: {data.basis.period_start} up to, excluding, {data.basis.period_end_exclusive} ({data.basis.timezone}).</p>
          <p>{data.counts.invoices} invoices; {data.counts.appeals} appeals.</p><p>{data.basis.unavailable}</p>
        </CardContent></Card>
      </>}
  </div>
}
