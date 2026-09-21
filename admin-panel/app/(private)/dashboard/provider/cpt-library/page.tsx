"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Search, BookOpen, Plus, ExternalLink } from "lucide-react"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Badge } from "@/components/ui/badge"

const SAMPLE_CODES = [
  { code: "99213", description: "Office or other outpatient visit, established patient", category: "Evaluation & Management", fee: "$120.00" },
  { code: "99214", description: "Office or other outpatient visit, established patient, detailed", category: "Evaluation & Management", fee: "$180.00" },
  { code: "72141", description: "MRI cervical spine; without contrast", category: "Radiology", fee: "$850.00" },
  { code: "97110", description: "Therapeutic procedure, 1 or more areas, each 15 minutes", category: "Physical Medicine", fee: "$65.00" },
  { code: "M54.2", description: "Cervicalgia (Neck pain)", category: "ICD-10 Diagnosis", fee: "N/A" },
  { code: "S13.4XXA", description: "Sprain of ligaments of cervical spine, initial encounter", category: "ICD-10 Diagnosis", fee: "N/A" },
]

export default function CPTLibraryPage() {
  return (
    <div className="space-y-8 p-6 animate-in fade-in duration-700">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
            <BookOpen className="h-8 w-8 text-primary" />
            CPT & ICD-10 Library
          </h1>
          <p className="text-slate-500 dark:text-slate-400 mt-1">
            Standardized medical coding reference for billing and claims submission.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" className="gap-2">
            <Plus className="h-4 w-4" /> Suggest Code
          </Button>
          <Button className="gap-2">
            <ExternalLink className="h-4 w-4" /> AMA Portal
          </Button>
        </div>
      </div>

      <Card className="bg-card/50 backdrop-blur-sm border-border">
        <CardHeader className="pb-3">
          <div className="flex items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input placeholder="Search by code, description, or category..." className="pl-10" />
            </div>
            <Button variant="secondary">Search</Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-lg border border-border overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/50">
                <TableRow>
                  <TableHead className="w-[120px]">Code</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead className="text-right">Estimated Fee</TableHead>
                  <TableHead className="w-[100px]"></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {SAMPLE_CODES.map((item) => (
                  <TableRow key={item.code} className="hover:bg-muted/20 transition-colors">
                    <TableCell className="font-bold text-primary">{item.code}</TableCell>
                    <TableCell className="max-w-md">{item.description}</TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="font-medium">
                        {item.category}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right font-semibold">{item.fee}</TableCell>
                    <TableCell>
                      <Button variant="ghost" size="sm" className="text-xs">Select</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <div className="mt-4 text-center">
            <p className="text-sm text-slate-500">
              Showing {SAMPLE_CODES.length} results. For more complex lookups, please use the AMA or CMS portal.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
