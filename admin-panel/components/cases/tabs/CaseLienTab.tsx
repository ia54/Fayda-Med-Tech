"use client"
import { LienRecords } from "@/components/cases/LienRecords"
export function CaseLienTab({ caseId }: { caseId: number }) {
  return <LienRecords key={caseId} caseId={caseId} />
}
