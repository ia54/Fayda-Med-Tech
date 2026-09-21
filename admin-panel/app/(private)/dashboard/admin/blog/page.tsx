"use client";

import { useMemo, useRef } from "react";
import { useBlogsTable } from "@/components/admin/blog";
import { getStatsCards, getPageHeader } from "@/components/admin/blog";
import { PageHeader, StatsCards } from "@/components/global/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  BlogsTableTab,
  CategoriesTableTab,
} from "@/components/admin/blog/tabs";

import { ProtectedRoute } from "@/components/protected-route";
import { useAuth } from "@/hooks/useAuth";
import { ROLES } from "@/lib/roleConstants";

export default function BlogPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <BlogPageContent />
    </ProtectedRoute>
  );
}

function BlogPageContent() {
  const { user } = useAuth();
  const isFirmAdmin = user?.role === ROLES.FIRM_ADMIN;
  // Reference to trigger Add Blog modal from BlogsTableTab
  const addBlogRef = useRef<{ openModal: () => void }>(null);

  // Data & API handlers (for stats and page header)
  const { stats, handleExport } = useBlogsTable();

  // Page configurations
  const statsCards = useMemo(() => getStatsCards(stats), [stats]);
  const pageHeader = useMemo(
    () => getPageHeader(() => addBlogRef.current?.openModal(), handleExport),
    [handleExport]
  );

  return (
    <div className="min-h-screen bg-transparent overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-4 md:space-y-6 max-w-full overflow-x-hidden">
        {/* Page Header - With Add Blog Button */}
        <PageHeader config={pageHeader} />

        {/* Stats Cards - Separate Component */}
        <StatsCards config={{ stats: statsCards }} />

        {/* Tabs Section */}
        <Tabs defaultValue="posts" className="space-y-6">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="posts">All Posts</TabsTrigger>
            <TabsTrigger value="categories">Categories</TabsTrigger>
          </TabsList>

          {/* All Posts Tab */}
          <TabsContent value="posts">
            <BlogsTableTab ref={addBlogRef} />
          </TabsContent>

          {/* Categories Tab */}
          <TabsContent value="categories">
            <CategoriesTableTab />
          </TabsContent>
        </Tabs>
      </div>
    </div>
  );
}
