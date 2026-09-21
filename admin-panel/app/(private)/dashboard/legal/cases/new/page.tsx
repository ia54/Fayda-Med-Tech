"use client"

import { useRouter } from "next/navigation"
import { CaseForm } from "@/components/cases/CaseForm"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { ChevronLeft, Scale } from "lucide-react"
import { ProtectedRoute } from "@/components/protected-route"
import { ROLES } from "@/lib/roleConstants"

export default function NewCasePage() {
  const router = useRouter()

  return (
    <ProtectedRoute requiredRole={[ROLES.FIRM_ADMIN, ROLES.ATTORNEY]}>
      <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-teal-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-teal-950/20 p-6 md:p-8">
        <div className="max-w-3xl mx-auto space-y-6">
          {/* Breadcrumbs / Back button */}
          <Button 
            variant="ghost" 
            className="group text-muted-foreground hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
            onClick={() => router.push("/dashboard/legal/cases")}
          >
            <ChevronLeft className="w-4 h-4 mr-2 group-hover:-translate-x-1 transition-transform" />
            Back to Cases
          </Button>

          {/* Header */}
          <div className="flex items-center gap-4 mb-2">
            <div className="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-2xl">
              <Scale className="w-8 h-8 text-emerald-600 dark:text-slate-300" />
            </div>
            <div>
              <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Create New Case</h1>
              <p className="text-slate-500 dark:text-slate-300">Initialize a new legal matter for your organization</p>
            </div>
          </div>

          <Card className="border-none shadow-2xl shadow-emerald-200/50 dark:shadow-emerald-950/50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm">
            <CardHeader className="border-b border-slate-100 dark:border-emerald-900/50 pb-6">
              <CardTitle className="dark:text-white">Case Information</CardTitle>
              <CardDescription className="dark:text-slate-300">
                Provide the essential details to begin tracking this legal case.
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-8">
              <CaseForm 
                onSuccess={() => router.push("/dashboard/legal/cases")}
                onCancel={() => router.push("/dashboard/legal/cases")}
              />
            </CardContent>
          </Card>

          {/* Tips Section */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 rounded-xl bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30">
              <h4 className="font-semibold text-blue-900 dark:text-blue-300 mb-1 flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                Case Linking
              </h4>
              <p className="text-sm text-blue-700/80 dark:text-blue-400/80">
                After creation, you can link medical claims and documents to this case number.
              </p>
            </div>
            <div className="p-4 rounded-xl bg-amber-50/50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30">
              <h4 className="font-semibold text-amber-900 dark:text-amber-300 mb-1 flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                Auto-Numbering
              </h4>
              <p className="text-sm text-amber-700/80 dark:text-amber-400/80">
                A unique case reference number will be automatically generated upon submission.
              </p>
            </div>
          </div>
        </div>
      </div>
    </ProtectedRoute>
  )
}
