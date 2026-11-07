'use client'

import type {ReactNode} from 'react'
import type {CompoundBatch} from '@/store/api/pharmacyApiSlice'

const steps = [
  {id: 'batch-plan', title: '1. Worksheet and review'},
  {id: 'batch-preparation', title: '2. Ingredients and preparation'},
  {id: 'batch-quality', title: '3. Quality and dating'},
  {id: 'batch-containers', title: '4. Packaging and custody'},
  {id: 'batch-labels', title: '5. Suitability and labels'},
  {id: 'batch-history', title: 'Record history'},
] as const

export function BatchWorkflowGuide({batch}: {batch: CompoundBatch}) {
  const reserved = batch.allocations.filter(item => item.status === 'reserved').length
  return <div className="rounded-lg border bg-muted/30 p-4 space-y-4">
    <h3 className="font-semibold">Batch workflow</h3>
    <dl className="grid gap-3 sm:grid-cols-3 text-sm">
      <div><dt className="text-muted-foreground">Worksheet</dt><dd>{batch.status.replaceAll('_', ' ')}</dd></div>
      <div><dt className="text-muted-foreground">Preparation record</dt><dd>{batch.execution ? batch.execution.status.replaceAll('_', ' ') : 'Not recorded'}</dd></div>
      <div><dt className="text-muted-foreground">Output status</dt><dd>{batch.output_status?.replaceAll('_', ' ') || 'Not recorded'}</dd></div>
    </dl>
    {batch.prescription_discontinued_at && <p role="alert" className="font-semibold text-destructive">The linked prescription is discontinued. New preparation is blocked. Review unused ingredient reservations and retained material below.</p>}
    {batch.incident_custody_hold && <p role="alert" className="font-semibold text-destructive">An ingredient-consumption incident is unresolved. Review the incident and custody records before proceeding.</p>}
    <p className="text-sm">{reserved} ingredient allocation{reserved === 1 ? '' : 's'} currently reserved. These statuses describe saved records; they do not establish release clearance. Quality, dating and container evidence must be checked in their sections.</p>
    <nav aria-label="Batch workflow sections" className="flex flex-wrap gap-x-5 gap-y-3 text-sm">
      {steps.map(step => <a key={step.id} href={`#${step.id}`} className="underline underline-offset-4 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">{step.title}</a>)}
    </nav>
    <p className="text-sm font-medium">Medication release and handover from compounded output remain unavailable.</p>
  </div>
}

// Keep every section mounted: navigation must not discard an unfinished form
// or hide a custody warning. Existing child-level deep links remain intact.
export function BatchWorkflowSection({id, title, description, children}: {
  id: string; title: string; description: string; children: ReactNode
}) {
  return <section id={id} aria-labelledby={`${id}-heading`} className="scroll-mt-24 space-y-4 border-t pt-6">
    <div>
      <h3 id={`${id}-heading`} className="text-lg font-semibold">{title}</h3>
      <p className="text-sm text-muted-foreground">{description}</p>
    </div>
    {children}
  </section>
}
