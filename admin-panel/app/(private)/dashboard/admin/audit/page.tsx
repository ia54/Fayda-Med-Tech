"use client"

import { useState } from "react"
import { DataTable } from "@/components/global/table/DataTable"
import { useGetAuditLogsQuery } from "@/store/api/auditApiSlice"
import { Badge } from "@/components/ui/badge"
import { format } from "date-fns"
import { Download, Eye } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";

export default function AuditLogsPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <AuditLogsPageContent />
    </ProtectedRoute>
  );
}

function AuditLogsPageContent() {
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(15)
  const [search, setSearch] = useState("")
  const [selectedLog, setSelectedLog] = useState<any>(null)
  const [isDetailOpen, setIsDetailOpen] = useState(false)

  const { data, isLoading, isError, refetch } = useGetAuditLogsQuery({
    page,
    per_page: pageSize,
    search: search || undefined,
  })

  const exportToExcel = () => {
    if (!data?.data) return

    // Create CSV content (Excel compatible with BOM)
    const headers = ["ID", "Event", "User", "Email", "Organization", "URL", "IP", "Date"]
    const rows = data.data.map(log => [
      log.id,
      log.event,
      `${log.user?.first_name} ${log.user?.last_name}` || "N/A",
      log.user?.email || "N/A",
      log.organization?.org_name || "N/A",
      log.url,
      log.ip_address,
      format(new Date(log.created_at), "yyyy-MM-dd HH:mm:ss")
    ])

    const csvContent = [
      "\uFEFF", // BOM for Excel UTF-8 support
      headers.join(","),
      ...rows.map(row => row.map(val => `"${String(val || "").replace(/"/g, '""')}"`).join(","))
    ].join("\n")

    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
    const url = URL.createObjectURL(blob)
    const link = document.createElement("a")
    link.href = url
    link.setAttribute("download", `AuditLogs_${format(new Date(), "yyyyMMdd_HHmmss")}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  const columns = [
    {
      id: "created_at",
      header: "Timestamp",
      accessorKey: "created_at",
      cell: ({ value }: any) => format(new Date(value), "MMM dd, HH:mm:ss"),
    },
    {
      id: "user",
      header: "User",
      accessorKey: "user",
      cell: ({ value }: any) => value ? `${value.first_name} ${value.last_name}` : "System",
    },
    {
      id: "event",
      header: "Event",
      accessorKey: "event",
      cell: ({ value }: any) => {
        const variants: Record<string, string> = {
          login: "default",
          profile_update: "secondary",
          document_upload: "info",
          case_view: "warning",
          settings_change: "destructive",
        }
        return (
          <Badge variant={(variants[value] as any) || "outline"}>
            {(value || "").replace("_", " ").toUpperCase()}
          </Badge>
        )
      },
    },
    {
      id: "organization",
      header: "Organization",
      accessorKey: "organization",
      cell: ({ value }: any) => value?.org_name || "Platform",
    },
    {
      id: "ip_address",
      header: "IP Address",
      accessorKey: "ip_address",
    },
    {
      id: "actions",
      header: "Details",
      cell: ({ row }: any) => (
        <Button variant="ghost" size="sm" onClick={() => {
          setSelectedLog(row.original)
          setIsDetailOpen(true)
        }}>
          <Eye className="w-4 h-4" />
        </Button>
      ),
    },
  ]

  return (
    <div className="min-h-screen bg-transparent overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-4 md:space-y-6 max-w-full overflow-x-hidden">
      <DataTable
        data={data?.data || []}
        columns={columns}
        title="Audit Logs"
        description="Monitor system activity and track user actions across the platform."
        loading={{ isLoading }}
        errorState={{ error: isError ? new Error("Failed to load audit logs") : null }}
        onSearchChange={setSearch}
        searchValue={search}
        customFilters={
          <Button variant="outline" size="sm" onClick={exportToExcel} disabled={!data?.data?.length}>
            <Download className="w-4 h-4 mr-2" />
            Export Excel
          </Button>
        }
        pagination={{
          page: page,
          pageSize: pageSize,
          total: data?.meta?.total || 0,
        }}
        paginationHandlers={{
          onPageChange: (p) => setPage(p),
          onPageSizeChange: (s) => {
            setPageSize(s)
            setPage(1)
          },
        }}
        onRefresh={() => { refetch() }}
      />

      <Dialog open={isDetailOpen} onOpenChange={setIsDetailOpen}>
        <DialogContent className="sm:max-w-[600px] max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Audit Log Details</DialogTitle>
          </DialogHeader>
          {selectedLog && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-muted-foreground font-medium">Event</p>
                  <p className="font-mono uppercase">{selectedLog.event}</p>
                </div>
                <div>
                  <p className="text-muted-foreground font-medium">Timestamp</p>
                  <p>{format(new Date(selectedLog.created_at), "yyyy-MM-dd HH:mm:ss")}</p>
                </div>
                <div>
                  <p className="text-muted-foreground font-medium">User</p>
                  <p>{selectedLog.user ? `${selectedLog.user.first_name} ${selectedLog.user.last_name}` : "System"}</p>
                </div>
                <div>
                  <p className="text-muted-foreground font-medium">IP Address</p>
                  <p>{selectedLog.ip_address}</p>
                </div>
              </div>

              <div>
                <p className="text-muted-foreground font-medium mb-1">User Agent</p>
                <p className="text-xs bg-muted p-2 rounded">{selectedLog.user_agent}</p>
              </div>

              {selectedLog.old_values && (
                <div>
                  <p className="text-muted-foreground font-medium mb-1">Old Values</p>
                  <pre className="text-xs bg-muted p-2 rounded overflow-x-auto">
                    {JSON.stringify(selectedLog.old_values, null, 2)}
                  </pre>
                </div>
              )}

              {selectedLog.new_values && (
                <div>
                  <p className="text-muted-foreground font-medium mb-1">New Values</p>
                  <pre className="text-xs bg-muted p-2 rounded overflow-x-auto">
                    {JSON.stringify(selectedLog.new_values, null, 2)}
                  </pre>
                </div>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
      </div>
    </div>
  )
}