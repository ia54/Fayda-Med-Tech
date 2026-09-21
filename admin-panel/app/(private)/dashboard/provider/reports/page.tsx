"use client"

import { useState } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
import { 
  BarChart3, 
  TrendingUp, 
  FileText, 
  Users, 
  DollarSign, 
  Calendar,
  ChevronRight,
  Download
} from "lucide-react"
import { useGetProviderStatsQuery } from "@/store/api/billingApiSlice"
import { LoadingSpinner } from "@/components/loading-spinner"
import { Progress } from "@/components/ui/progress"

export default function ProviderReportsPage() {
  const { data: providerData, isLoading } = useGetProviderStatsQuery()
  
  if (isLoading) return <LoadingSpinner />

  const stats = (providerData?.data?.stats || {}) as any
  const revenueData = providerData?.data?.monthly_revenue || []
  const statusDistribution = providerData?.data?.status_distribution || []

  return (
    <div className="space-y-8 p-6">
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold text-emerald-900">Clinical & Financial Reports</h1>
          <p className="text-emerald-700/70 mt-1">Practice performance and revenue collection analytics</p>
        </div>
        <Button className="bg-emerald-600 hover:bg-emerald-700">
          <Download className="h-4 w-4 mr-2" />
          Export Clinical Data
        </Button>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-emerald-100 text-emerald-600">
                <Users className="h-6 w-6" />
              </div>
              <div>
                <p className="text-sm font-medium text-emerald-600/70 uppercase tracking-tight">Total Patients</p>
                <h3 className="text-2xl font-bold text-emerald-900">{stats.total_claims || 0}</h3>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-emerald-100 text-emerald-600">
                <DollarSign className="h-6 w-6" />
              </div>
              <div>
                <p className="text-sm font-medium text-emerald-600/70 uppercase tracking-tight">Total Collected</p>
                <h3 className="text-2xl font-bold text-emerald-900">{stats.total_revenue || "$0.00"}</h3>
              </div>
            </div>
          </CardContent>
        </Card>
        <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
          <CardContent className="p-6">
            <div className="flex items-center gap-4">
              <div className="p-3 rounded-xl bg-emerald-100 text-emerald-600">
                <TrendingUp className="h-6 w-6" />
              </div>
              <div>
                <p className="text-sm font-medium text-emerald-600/70 uppercase tracking-tight">Collection Rate</p>
                <h3 className="text-2xl font-bold text-emerald-900">{stats.success_rate || "0%"}</h3>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Revenue Trends */}
        <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Revenue Trends
            </CardTitle>
            <CardDescription>Monthly payment collections over the last 6 months</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {revenueData.length > 0 ? revenueData.map((item: any, i: number) => (
              <div key={i} className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="font-bold text-emerald-800">{item.month}</span>
                  <span className="font-medium text-emerald-600">${Number(item.total).toLocaleString()}</span>
                </div>
                <Progress value={(item.total / (Math.max(...revenueData.map((r:any) => r.total)) || 1)) * 100} className="h-2 bg-emerald-50" />
              </div>
            )) : (
              <div className="py-12 text-center text-emerald-600/50 italic">No revenue data available</div>
            )}
          </CardContent>
        </Card>

        {/* Claim Distribution */}
        <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Claim Lifecycle
            </CardTitle>
            <CardDescription>Breakdown of current clinical inventory</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {statusDistribution.map((item: any, i: number) => (
              <div key={i} className="flex items-center justify-between p-4 rounded-xl bg-emerald-50/30 border border-emerald-100/50 hover:bg-emerald-50/50 transition-all cursor-pointer">
                <div className="flex items-center gap-3">
                  <div className={`w-3 h-3 rounded-full ${item.color}`} />
                  <span className="font-bold text-emerald-900">{item.status}</span>
                </div>
                <div className="flex items-center gap-6">
                  <span className="text-lg font-bold text-emerald-700">{item.percentage}%</span>
                  <ChevronRight className="h-4 w-4 text-emerald-300" />
                </div>
              </div>
            ))}
            {statusDistribution.length === 0 && (
              <div className="py-12 text-center text-emerald-600/50 italic">No claim distribution data</div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Available Templates */}
      <Card className="border-emerald-100 bg-white/70 backdrop-blur-sm shadow-sm">
        <CardHeader>
          <CardTitle className="text-emerald-900">Standardized Report Templates</CardTitle>
          <CardDescription>Select a template to generate a detailed clinical report</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {[
              { title: "Daily Treatment Log", desc: "List of all patients seen and services rendered today.", icon: Calendar },
              { title: "Collection Analysis", desc: "Detailed breakdown of payments by insurance carrier.", icon: DollarSign },
              { title: "Outstanding Liens", desc: "Inventory of active liens awaiting settlement.", icon: FileText },
            ].map((template, idx) => (
              <Button key={idx} variant="outline" className="h-auto p-4 flex flex-col items-start gap-2 border-emerald-50 hover:bg-emerald-50 hover:border-emerald-200 transition-all text-left group">
                <div className="p-2 rounded-lg bg-emerald-100 text-emerald-600 mb-2 group-hover:scale-110 transition-transform">
                  <template.icon className="h-5 w-5" />
                </div>
                <h4 className="font-bold text-emerald-900">{template.title}</h4>
                <p className="text-xs text-emerald-700/60 leading-relaxed">{template.desc}</p>
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
