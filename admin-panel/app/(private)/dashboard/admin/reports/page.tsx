"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import {
  useGetCaseStatusReportQuery,
  useGetRevenueByPeriodReportQuery,
  useGetInsuranceAgingReportQuery,
  useGetSettlementSummaryReportQuery,
  useGetAttorneyProductionReportQuery,
  useGetProviderBillingReportQuery,
  useGetLienSummaryReportQuery,
  useGetOcrProcessingLogReportQuery,
  useGetSignatureActivityReportQuery,
  useGetUserActivityLogQuery,
  useGetDocumentAuditTrailQuery,
  useGetHipaaComplianceLogQuery,
  useGetCollectionRateReportQuery,
  useGetReferralSourceReportQuery,
  useGetReportHistoryQuery,
} from "@/store/api/apiSlice"
import { Badge } from "@/components/ui/badge"
import { Skeleton } from "@/components/ui/skeleton"

const reportCategories = [
  {
    id: "cases",
    label: "Case Reports",
    reports: [
      { key: "caseStatus", label: "Case Status", hook: useGetCaseStatusReportQuery },
      { key: "settlementSummary", label: "Settlement Summary", hook: useGetSettlementSummaryReportQuery },
      { key: "attorneyProduction", label: "Attorney Production", hook: useGetAttorneyProductionReportQuery },
      { key: "referralSource", label: "Referral Source", hook: useGetReferralSourceReportQuery },
    ],
  },
  {
    id: "billing",
    label: "Billing Reports",
    reports: [
      { key: "revenueByPeriod", label: "Revenue by Period", hook: useGetRevenueByPeriodReportQuery },
      { key: "insuranceAging", label: "Insurance Aging", hook: useGetInsuranceAgingReportQuery },
      { key: "providerBilling", label: "Provider Billing", hook: useGetProviderBillingReportQuery },
      { key: "collectionRate", label: "Collection Rate", hook: useGetCollectionRateReportQuery },
    ],
  },
  {
    id: "provider",
    label: "Provider & Lien Reports",
    reports: [
      { key: "lienSummary", label: "Lien Summary", hook: useGetLienSummaryReportQuery },
      { key: "providerBillingLien", label: "Provider Billing", hook: useGetProviderBillingReportQuery },
    ],
  },
  {
    id: "operations",
    label: "Operations Reports",
    reports: [
      { key: "ocrProcessingLog", label: "OCR Processing Log", hook: useGetOcrProcessingLogReportQuery },
      { key: "signatureActivity", label: "Signature Activity", hook: useGetSignatureActivityReportQuery },
    ],
  },
  {
    id: "compliance",
    label: "Compliance Reports",
    reports: [
      { key: "userActivityLog", label: "User Activity Log", hook: useGetUserActivityLogQuery },
      { key: "documentAuditTrail", label: "Document Audit Trail", hook: useGetDocumentAuditTrailQuery },
      { key: "hipaaComplianceLog", label: "HIPAA Compliance Log", hook: useGetHipaaComplianceLogQuery },
    ],
  },
]

function ReportCard({ title, data, loading }: { title: string; data: any; loading: boolean }) {
  if (loading) {
    return (
      <Card>
        <CardHeader><Skeleton className="h-5 w-40" /></CardHeader>
        <CardContent><Skeleton className="h-20 w-full" /></CardContent>
      </Card>
    )
  }

  const summary = data?.data?.result_summary || data?.data?.data || {}
  const entries = Object.entries(summary).filter(([k]) => !['cases', 'results', 'aging', 'production', 'billing', 'sources', 'by_provider'].includes(k))

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-lg">{title}</CardTitle>
        <CardDescription>Generated {data?.data?.completed_at ? new Date(data.data.completed_at).toLocaleString() : 'now'}</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          {entries.map(([key, value]: [string, any]) => (
            <div key={key} className="bg-muted/50 rounded-lg p-3">
              <div className="text-xs text-muted-foreground uppercase tracking-wider">{key.replace(/_/g, ' ')}</div>
              <div className="text-xl font-bold mt-1">
                {typeof value === 'number' ? value.toLocaleString('en-US', { style: 'currency', currency: 'USD' }).replace(/\.00$/, '') : String(value)}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}

export default function ReportsPage() {
  const [activeTab, setActiveTab] = useState("cases")
  const { data: reportHistory } = useGetReportHistoryQuery({ per_page: 10 })

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Reports & Analytics</h1>
        <p className="text-muted-foreground">
          Generate and view reports across cases, billing, operations, and compliance (PDF Section 11)
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="flex-wrap">
          {reportCategories.map((cat) => (
            <TabsTrigger key={cat.id} value={cat.id}>{cat.label}</TabsTrigger>
          ))}
          <TabsTrigger value="history">Report History</TabsTrigger>
        </TabsList>

        {reportCategories.map((cat) => (
          <TabsContent key={cat.id} value={cat.id} className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              {cat.reports.map((report) => {
                const { data, isLoading } = report.hook({})
                return <ReportCard key={report.key} title={report.label} data={data} loading={isLoading} />
              })}
            </div>
          </TabsContent>
        ))}

        <TabsContent value="history">
          <Card>
            <CardHeader>
              <CardTitle>Generated Reports</CardTitle>
              <CardDescription>Previously generated reports with their summaries</CardDescription>
            </CardHeader>
            <CardContent>
              {reportHistory?.data?.data?.length > 0 ? (
                <div className="space-y-2">
                  {reportHistory.data.data.map((report: any) => (
                    <div key={report.id} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                      <div>
                        <div className="font-medium">{report.report_name}</div>
                        <div className="text-sm text-muted-foreground">
                          {report.report_type} • {report.format?.toUpperCase()} • Generated {new Date(report.created_at).toLocaleDateString()}
                        </div>
                      </div>
                      <Badge variant={report.status === 'completed' ? 'default' : 'secondary'}>
                        {report.status}
                      </Badge>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-muted-foreground">No reports generated yet. Visit the report tabs above to generate reports.</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}