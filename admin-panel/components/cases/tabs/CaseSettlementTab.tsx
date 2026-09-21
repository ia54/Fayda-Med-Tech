"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { 
  Calculator, 
  DollarSign, 
  Percent, 
  Wallet,
  Briefcase,
  UserCheck,
  FileCheck2,
  ArrowRight
} from "lucide-react"
import { useGetSettlementsQuery, useCreateSettlementMutation } from "@/store/api/apiSlice"
import { useToast } from "@/hooks/use-toast"
import { LoadingSpinner } from "@/components/loading-spinner"

export function CaseSettlementTab({ caseId }: { caseId: number }) {
  const { data: settlementResponse, isLoading, refetch } = useGetSettlementsQuery({ case_id: caseId })
  const [createSettlement, { isLoading: isCreating }] = useCreateSettlementMutation()
  const { toast } = useToast()

  const [calcData, setCalcData] = useState({
    gross_amount: "0",
    fee_percentage: "33.33",
    litigation_costs: "0",
    other_deductions: "0"
  })

  const settlement = settlementResponse?.data?.data?.[0] // Assuming one main settlement record for now

  // Calculations
  const gross = parseFloat(calcData.gross_amount) || 0
  const feePercent = parseFloat(calcData.fee_percentage) || 0
  const fees = (gross * feePercent) / 100
  const costs = parseFloat(calcData.litigation_costs) || 0
  const other = parseFloat(calcData.other_deductions) || 0
  const netToClient = gross - fees - costs - other

  const handleSaveSettlement = async () => {
    try {
      await createSettlement({
        case_id: caseId,
        settlement_amount: gross,
        settlement_date: new Date().toISOString().split('T')[0],
        status: "completed",
        notes: `Breakdown: Fees(${feePercent}%) = $${fees.toFixed(2)}, Costs = $${costs.toFixed(2)}, Net = $${netToClient.toFixed(2)}`
      }).unwrap()
      toast({ title: "Settlement Recorded", description: "Financial breakdown saved successfully" })
      refetch()
    } catch (error) {
      toast({ title: "Error", description: "Failed to save settlement", variant: "destructive" })
    }
  }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-bold text-emerald-900 dark:text-white">Settlement Breakdown</h3>
          <p className="text-sm text-slate-500">Calculate net recovery and firm fees</p>
        </div>
        {settlement && <Badge className="bg-emerald-600">Settled: ${parseFloat(settlement.settlement_amount).toLocaleString()}</Badge>}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Calculation Form */}
        <Card className="border-emerald-100 bg-white shadow-sm h-fit">
          <CardHeader className="bg-emerald-50/50 border-b border-emerald-100">
            <CardTitle className="text-sm font-bold flex items-center gap-2">
              <Calculator className="w-4 h-4 text-emerald-600" /> Financial Input
            </CardTitle>
          </CardHeader>
          <CardContent className="p-6 space-y-4">
            <div className="space-y-2">
              <label className="text-sm font-bold text-slate-700">Gross Settlement Amount ($)</label>
              <div className="relative">
                <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <Input 
                  type="number" 
                  className="pl-9 font-bold text-lg" 
                  value={calcData.gross_amount}
                  onChange={e => setCalcData({...calcData, gross_amount: e.target.value})}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <label className="text-sm font-bold text-slate-700">Attorney Fee %</label>
                <div className="relative">
                  <Percent className="absolute right-3 top-1/2 -translate-y-1/2 h-3 w-3 text-slate-400" />
                  <Input 
                    type="number" 
                    value={calcData.fee_percentage}
                    onChange={e => setCalcData({...calcData, fee_percentage: e.target.value})}
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-sm font-bold text-slate-700">Litigation Costs ($)</label>
                <Input 
                  type="number" 
                  value={calcData.litigation_costs}
                  onChange={e => setCalcData({...calcData, litigation_costs: e.target.value})}
                />
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-bold text-slate-700">Other Deductions / Liens ($)</label>
              <Input 
                type="number" 
                value={calcData.other_deductions}
                onChange={e => setCalcData({...calcData, other_deductions: e.target.value})}
              />
            </div>

            <Button className="w-full bg-emerald-600 hover:bg-emerald-700 mt-4" onClick={handleSaveSettlement} disabled={isCreating}>
              <FileCheck2 className="w-4 h-4 mr-2" />
              Finalize Settlement
            </Button>
          </CardContent>
        </Card>

        {/* Results Summary */}
        <div className="space-y-6">
          <Card className="border-0 bg-slate-900 text-white shadow-xl overflow-hidden relative">
            <div className="absolute top-0 right-0 p-8 opacity-10">
              <Wallet className="w-24 h-24" />
            </div>
            <CardHeader>
              <CardDescription className="text-slate-400 uppercase text-[10px] font-bold tracking-widest">Net Recovery to Client</CardDescription>
              <CardTitle className="text-5xl font-black text-white mt-2">${netToClient.toLocaleString(undefined, {minimumFractionDigits: 2})}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3 mt-4">
                <div className="flex justify-between items-center text-sm">
                  <span className="text-slate-400 flex items-center gap-2"><Briefcase className="w-3 h-3" /> Firm Attorney Fees</span>
                  <span className="font-bold text-emerald-400">${fees.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
                <div className="flex justify-between items-center text-sm">
                  <span className="text-slate-400 flex items-center gap-2"><ArrowRight className="w-3 h-3" /> Reimbursable Costs</span>
                  <span className="font-bold text-slate-200">${costs.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
                <div className="flex justify-between items-center text-sm pt-3 border-t border-slate-800">
                  <span className="text-slate-400">Total Deductions</span>
                  <span className="font-bold text-rose-400">-${(fees + costs + other).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="grid grid-cols-2 gap-4">
            <div className="p-4 rounded-xl bg-emerald-50 border border-emerald-100 flex flex-col gap-1">
              <p className="text-[10px] uppercase font-bold text-emerald-700">Firm Revenue</p>
              <p className="text-xl font-bold text-emerald-900">${(fees + costs).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
            </div>
            <div className="p-4 rounded-xl bg-blue-50 border border-blue-100 flex flex-col gap-1">
              <p className="text-[10px] uppercase font-bold text-blue-700">Client Payout %</p>
              <p className="text-xl font-bold text-blue-900">{gross > 0 ? ((netToClient / gross) * 100).toFixed(1) : "0"}%</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
