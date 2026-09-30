"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Plus, Search, FileText } from "lucide-react"
import { Input } from "@/components/ui/input"
import { type Appeal, useGetAppealsQuery } from "@/store/api/billingApiSlice"
import { Skeleton } from "@/components/ui/skeleton"
import Link from "next/link"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog"

export default function AppealsPage() {
  const [searchTerm, setSearchTerm] = useState("")
  const [selectedAppeal, setSelectedAppeal] = useState<Appeal | null>(null)
  const [statusFilter, setStatusFilter] = useState("")
  const [page, setPage] = useState(1)
  const { data: appealsData, isLoading, isFetching, isError, refetch } = useGetAppealsQuery({ search: searchTerm, status: statusFilter || undefined, page })

  const getStatusColor = (status: string) => {
    switch (status) {
      case "accepted": return "bg-emerald-100 text-emerald-700 border-emerald-200"
      case "sent": return "bg-blue-100 text-blue-700 border-blue-200"
      case "rejected": return "bg-red-100 text-red-700 border-red-200"
      case "draft": return "bg-slate-100 text-slate-700 border-slate-200"
      default: return "bg-slate-100 text-slate-700"
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-3xl font-bold text-primary">Appeal Draft History</h1>
          <p className="text-muted-foreground">Manage saved appeal drafts awaiting human review</p>
        </div>
        <Link href="/dashboard/billing/appeals/create">
          <Button>
            <Plus className="h-4 w-4 mr-2" />
            New Appeal Draft
          </Button>
        </Link>
      </div>

      <Card className="bg-card/80 backdrop-blur-sm">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by appeal number or invoice..."
                className="pl-10"
                value={searchTerm}
                onChange={(e) => { setSearchTerm(e.target.value); setPage(1) }}
              />
            </div>
            <select aria-label="Filter appeal status" value={statusFilter} onChange={(event) => { setStatusFilter(event.target.value); setPage(1) }} className="h-10 rounded-md border bg-background px-3">
              <option value="">All statuses</option><option value="draft">Draft</option><option value="sent">Sent</option><option value="accepted">Accepted</option><option value="rejected">Rejected</option>
            </select>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : isError ? (
            <div role="alert">Appeal records could not be loaded. <Button variant="outline" onClick={() => refetch()}>Retry</Button></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead>Appeal #</TableHead>
                    <TableHead>Invoice #</TableHead>
                    <TableHead>Category</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Generated At</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {appealsData?.data?.data?.map((appeal) => (
                    <TableRow key={appeal.id}>
                      <TableCell className="font-medium">{appeal.appeal_number}</TableCell>
                      <TableCell>{appeal.invoice?.invoice_number || "N/A"}</TableCell>
                      <TableCell>{appeal.reason_category}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className={getStatusColor(appeal.status)}>
                          {appeal.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {new Date(appeal.created_at).toLocaleDateString()}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => setSelectedAppeal(appeal)} aria-label={`View appeal ${appeal.appeal_number}`}>
                          <FileText className="h-4 w-4 mr-2" />View draft
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {appealsData?.data?.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="h-24 text-center">
                        No appeals found. Click "New Appeal Draft" to start.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
          <div className="flex items-center justify-end gap-3 mt-4">
            <Button variant="outline" disabled={isFetching || page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
            <span>Page {page}</span>
            <Button variant="outline" disabled={isFetching || !appealsData?.data?.next_page_url} onClick={() => setPage(page + 1)}>Next</Button>
          </div>
        </CardContent>
      </Card>
      <Dialog open={selectedAppeal !== null} onOpenChange={(open) => { if (!open) setSelectedAppeal(null) }}>
        <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto">
          <DialogHeader><DialogTitle>Appeal {selectedAppeal?.appeal_number}</DialogTitle><DialogDescription>Saved record for human review. This screen does not send anything to a payer.</DialogDescription></DialogHeader>
          <p className="text-sm">Recorded status: {selectedAppeal?.status}</p>
          <div className="whitespace-pre-wrap text-sm">{selectedAppeal?.content}</div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
