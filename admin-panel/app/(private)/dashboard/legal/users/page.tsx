"use client";

import { useMemo, useRef } from "react";
import { useUsersTable } from "@/components/admin/users/useUsersTable";
import {
  getStatsCards,
  getPageHeader,
} from "@/components/admin/users/usersPageConfig";
import { PageHeader, StatsCards } from "@/components/global/table";
import { UsersTableTab } from "@/components/admin/users/tabs/UsersTableTab";
import { useToast } from "@/hooks/use-toast";
import { ProtectedRoute } from "@/components/protected-route";
import { useAuth } from "@/hooks/useAuth";
import { ROLES } from "@/lib/roleConstants";

export default function FirmUserManagementPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
      <FirmUserManagementContent />
    </ProtectedRoute>
  );
}

function FirmUserManagementContent() {
  const { user } = useAuth();
  const { toast } = useToast();

  // Reference to trigger Add User modal from UsersTableTab
  const addUserRef = useRef<{ openModal: () => void }>(null);

  // Data & API handlers (for stats and page header)
  const { stats, users } = useUsersTable();

  // Export handler
  const handleExport = async (format: "csv" | "excel" | "json" | "pdf") => {
    try {
      // Transform data for export
      const exportData = users.map((user) => ({
        ID: user?.id || "N/A",
        "First Name": user?.first_name || "N/A",
        "Last Name": user?.last_name || "N/A",
        Email: user?.email || "N/A",
        Role: user?.role || "N/A",
        Organization: user?.organization || "N/A",
        Status: user?.status || "N/A",
        "Last Login": user?.last_login
          ? new Date(user.last_login).toLocaleString()
          : "Never",
        "Email Verified": user?.email_verified_at ? "Yes" : "No",
        "Created At": user?.created_at
          ? new Date(user.created_at).toLocaleDateString()
          : "N/A",
      }));

      // Dynamic import to reduce bundle size
      const { exportToCSV, exportToExcel, exportToJSON } = await import(
        "@/components/global/table/utils/exportHelpers"
      );

      const filename = `firm_users_${new Date().toISOString().split("T")[0]}`;

      switch (format) {
        case "csv":
          exportToCSV(exportData, `${filename}.csv`);
          break;
        case "excel":
          exportToExcel(exportData, `${filename}.xlsx`);
          break;
        case "json":
          exportToJSON(exportData, `${filename}.json`);
          break;
        case "pdf":
          console.warn("PDF export not yet implemented");
          break;
      }

      toast({
        title: "Exported",
        description: `Users exported as ${format.toUpperCase()}.`,
      });
    } catch (error) {
      toast({
        title: "Error",
        description: "Failed to export users",
        variant: "destructive",
      });
    }
  };

  // Page configurations
  const statsCards = useMemo(() => getStatsCards(stats), [stats]);
  const pageHeader = useMemo(
    () => getPageHeader(() => addUserRef.current?.openModal(), handleExport),
    [handleExport]
  );

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-4 md:space-y-6 max-w-full overflow-x-hidden">
        {/* Page Header - With Add User Button */}
        <PageHeader config={pageHeader} />

        {/* Stats Cards - Separate Component */}
        <StatsCards config={{ stats: statsCards }} />

        {/* Users Table Directly (No Tabs for Firm Admin) */}
        <div className="mt-8">
            <UsersTableTab ref={addUserRef} />
        </div>
      </div>
    </div>
  );
}
