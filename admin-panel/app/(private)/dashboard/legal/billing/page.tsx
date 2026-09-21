"use client";

import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  CreditCard,
  DollarSign,
  TrendingUp,
} from "lucide-react";
import { SubscriptionPlansTableTab } from "@/components/admin/organizations/tabs";

import { useAuth } from "@/hooks/useAuth";
import { format } from "date-fns";
import { Skeleton } from "@/components/ui/skeleton";
import { useGetFirmOrganizationQuery } from "@/store/api/firmApiSlice";

export default function FirmBillingManagementPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
      <FirmBillingManagementContent />
    </ProtectedRoute>
  );
}

function FirmBillingManagementContent() {
  const { user } = useAuth();
  const { data: orgData, isLoading: isOrgLoading } = useGetFirmOrganizationQuery();
  
  const org = orgData?.data;
  
  // Mock transactions for now, but in a real app these would come from an API
  const recentTransactions = [
    {
      id: "FT-TXN-1024",
      plan: org?.subscription_plan || "Professional",
      amount: "$299.00",
      date: format(new Date(), "yyyy-MM-01"),
      status: "Paid",
    },
    {
      id: "FT-TXN-1023",
      plan: org?.subscription_plan || "Professional",
      amount: "$299.00",
      date: format(new Date(new Date().setMonth(new Date().getMonth() - 1)), "yyyy-MM-01"),
      status: "Paid",
    },
  ];

  if (isOrgLoading) {
    return (
      <div className="p-8 space-y-6">
        <Skeleton className="h-10 w-48" />
        <div className="grid grid-cols-3 gap-6">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-transparent p-4 md:p-8 space-y-8">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Billing & Plans</h1>
          <p className="text-emerald-600 dark:text-slate-300 mt-2">
            View subscription plans and transaction history for {org?.org_name}
          </p>
        </div>
        <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 border-none px-4 py-1">
          {org?.subscription_plan} Account
        </Badge>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">
              Current Plan
            </CardTitle>
            <CreditCard className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900 dark:text-white capitalize">{org?.subscription_plan}</div>
            <p className="text-xs text-emerald-600 dark:text-slate-300">
              Next billing date: {format(new Date(new Date().setMonth(new Date().getMonth() + 1)), "MMM dd, yyyy")}
            </p>
          </CardContent>
        </Card>

        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">
              Estimated Monthly Cost
            </CardTitle>
            <DollarSign className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900 dark:text-white">$299.00</div>
            <p className="text-xs text-emerald-600 dark:text-slate-300">Based on current plan</p>
          </CardContent>
        </Card>

        <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-lg transition-all duration-300">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700 dark:text-white">Firm Users</CardTitle>
            <TrendingUp className="h-4 w-4 text-emerald-600 dark:text-slate-300" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900 dark:text-white">{org?.no_of_employees || 'N/A'}</div>
            <p className="text-xs text-emerald-600 dark:text-slate-300">
              Allocated team members
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Main Content */}
      <Tabs defaultValue="plans" className="space-y-6">
        <TabsList className="bg-emerald-50/50 p-1 border border-emerald-100">
          <TabsTrigger value="plans">Available Plans</TabsTrigger>
          <TabsTrigger value="transactions">Transaction History</TabsTrigger>
        </TabsList>

        <TabsContent value="plans" className="space-y-6">
          <SubscriptionPlansTableTab />
        </TabsContent>

        <TabsContent value="transactions" className="space-y-6">
          <Card className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 overflow-hidden">
            <CardHeader>
              <CardTitle className="text-emerald-900 dark:text-white">Transaction History</CardTitle>
              <CardDescription className="dark:text-slate-300">
                Billing transactions for {org?.org_name}
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader className="bg-emerald-50/50">
                  <TableRow>
                    <TableHead className="text-emerald-900 font-bold uppercase text-[10px] tracking-wider px-6">ID</TableHead>
                    <TableHead className="text-emerald-900 font-bold uppercase text-[10px] tracking-wider px-6">Plan</TableHead>
                    <TableHead className="text-emerald-900 font-bold uppercase text-[10px] tracking-wider px-6">Amount</TableHead>
                    <TableHead className="text-emerald-900 font-bold uppercase text-[10px] tracking-wider px-6">Date</TableHead>
                    <TableHead className="text-emerald-900 font-bold uppercase text-[10px] tracking-wider px-6">Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {recentTransactions.map((transaction) => (
                    <TableRow key={transaction.id} className="hover:bg-emerald-50/30 transition-colors">
                      <TableCell className="px-6 py-4 font-medium text-emerald-950">{transaction.id}</TableCell>
                      <TableCell className="px-6 py-4 capitalize">{transaction.plan}</TableCell>
                      <TableCell className="px-6 py-4 font-bold">{transaction.amount}</TableCell>
                      <TableCell className="px-6 py-4 text-muted-foreground">{transaction.date}</TableCell>
                      <TableCell className="px-6 py-4">
                        <Badge
                          variant={transaction.status === "Paid" ? "default" : "outline"}
                          className={transaction.status === "Paid" ? "bg-emerald-600" : ""}
                        >
                          {transaction.status}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
