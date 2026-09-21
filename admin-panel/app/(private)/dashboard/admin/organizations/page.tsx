"use client";

import { useMemo, useRef } from "react";
import { useOrganizationsTable } from "@/components/admin/organizations/useOrganizationsTable";
import {
  getStatsCards,
  getPageHeader,
} from "@/components/admin/organizations/organizationsPageConfig";
import { PageHeader, StatsCards } from "@/components/global/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  OrganizationsTableTab,
  OrganizationTypesTableTab,
  SubscriptionPlansTableTab,
} from "@/components/admin/organizations/tabs";

import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";

export default function OrganizationsPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <OrganizationsPageContent />
    </ProtectedRoute>
  );
}

function OrganizationsPageContent() {
  // Reference to trigger Add Organization modal from OrganizationsTableTab
  const addOrganizationRef = useRef<{ openModal: () => void }>(null);

  // Data & API handlers (for stats and page header)
  const { stats, handleExport } = useOrganizationsTable();

  // Page configurations
  const statsCards = useMemo(() => getStatsCards(stats), [stats]);
  const pageHeader = useMemo(
    () =>
      getPageHeader(
        () => addOrganizationRef.current?.openModal(),
        handleExport,
      ),
    [handleExport],
  );

  return (
    <div className="min-h-screen bg-transparent overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-4 md:space-y-6 max-w-full overflow-x-hidden">
        {/* Page Header - With Add Organization Button */}
        <PageHeader config={pageHeader} />

        {/* Stats Cards - Separate Component */}
        <StatsCards config={{ stats: statsCards }} />

        {/* Tabs Section */}
        <Tabs defaultValue="organizations" className="space-y-6">
          <TabsList className="grid w-full grid-cols-3">
            <TabsTrigger value="organizations">All Organizations</TabsTrigger>
            <TabsTrigger value="types">Organization Types</TabsTrigger>
            <TabsTrigger value="plans">Subscription Plans</TabsTrigger>
          </TabsList>

          {/* All Organizations Tab */}
          <TabsContent value="organizations">
            <OrganizationsTableTab ref={addOrganizationRef} />
          </TabsContent>

          {/* Organization Types Tab */}
          <TabsContent value="types">
            <OrganizationTypesTableTab />
          </TabsContent>

          {/* Subscription Plans Tab */}
          <TabsContent value="plans">
            <SubscriptionPlansTableTab />
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
}