"use client"

import { useState } from "react"
import { DataTable } from "@/components/global/table/DataTable"
import { useGetCasesQuery, useDeleteCaseMutation } from "@/store/api/casesApiSlice"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Eye, Plus, Edit, Trash2 } from "lucide-react"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { CaseForm } from "@/components/cases/CaseForm"
import { useRouter } from "next/navigation"
import { toast } from "sonner"
import { format } from "date-fns"

export default function CasesDashboard() {
  const router = useRouter()
  const [page, setPage] = useState(1)
  const [pageSize, setPageSize] = useState(15)
  const [search, setSearch] = useState("")
  const [status, setStatus] = useState<string | undefined>(undefined)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedCase, setSelectedCase] = useState<any>(null)

  const { data, isLoading, isError, refetch } = useGetCasesQuery({
    page,
    per_page: pageSize,
    search: search || undefined,
    status: status !== "all" ? status : undefined,
  })

  const [deleteCase] = useDeleteCaseMutation()

  const handleDelete = async (id: number) => {
    if (confirm("Are you sure you want to delete this case?")) {
      try {
        await deleteCase(id).unwrap()
        toast.success("Case deleted successfully")
      } catch (error) {
        toast.error("Failed to delete case")
      }
    }
  }

  const columns = [
    {
      id: "case_number",
      header: "Case Number",
      accessorKey: "case_number",
      cell: ({ value }: any) => <span className="font-medium text-primary dark:text-white">{value}</span>,
    },
    {
      id: "title",
      header: "Title",
      accessorKey: "title",
    },
    {
      id: "accident_date",
      header: "Accident Date",
      accessorKey: "accident_date",
      cell: ({ value }: any) => value ? format(new Date(value), "MMM dd, yyyy") : "N/A",
    },
    {
      id: "total_case_value",
      header: "Value",
      accessorKey: "total_case_value",
      cell: ({ value }: any) => `$${parseFloat(value || 0).toLocaleString()}`,
    },
    {
      id: "status",
      header: "Status",
      accessorKey: "status",
      cell: ({ value }: any) => {
        const variants: Record<string, string> = {
          open: "default",
          active: "secondary",
          pending_settlement: "warning",
          settled: "success",
          closed: "outline",
        }
        return (
          <Badge variant={(variants[value] as any) || "outline"}>
            {(value || "").replace("_", " ").toUpperCase()}
          </Badge>
        )
      },
    },
    {
      id: "actions",
      header: "Actions",
      cell: ({ row }: any) => (
        <div className="flex gap-2 justify-center">
          <Button variant="ghost" size="sm" onClick={() => router.push(`/dashboard/legal/cases/${row.original.id}`)}>
            <Eye className="w-4 h-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={() => {
            setSelectedCase(row.original)
            setIsFormOpen(true)
          }}>
            <Edit className="w-4 h-4" />
          </Button>
          <Button variant="ghost" size="sm" className="text-destructive" onClick={() => handleDelete(row.original.id)}>
            <Trash2 className="w-4 h-4" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="min-h-screen bg-gradient-to-br from-teal-50/50 via-white to-cyan-50/30 dark:from-teal-950/20 dark:via-slate-950 dark:to-cyan-950/20 p-6">
      <div className="max-w-7xl mx-auto space-y-8">
        <DataTable
          data={data?.data || []}
          columns={columns}
          title="Case Management"
          description="Manage legal cases, track status, and link claims."
          loading={{ isLoading }}
          errorState={{ error: isError ? new Error("Failed to load cases") : null }}
          onSearchChange={setSearch}
          searchValue={search}
          addNewButton={{
            label: "Add Case",
            icon: <Plus className="w-4 h-4" />,
            onClick: () => router.push("/dashboard/legal/cases/new")
          }}
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
          className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50"
        />

        <Dialog open={isFormOpen} onOpenChange={setIsFormOpen}>
          <DialogContent className="sm:max-w-[600px]">
            <DialogHeader>
              <DialogTitle>{selectedCase ? "Edit Case" : "Add New Case"}</DialogTitle>
            </DialogHeader>
            <CaseForm
              initialData={selectedCase}
              onSuccess={() => {
                setIsFormOpen(false)
                refetch()
              }}
              onCancel={() => setIsFormOpen(false)}
            />
          </DialogContent>
        </Dialog>
      </div>
    </div>
  )
}