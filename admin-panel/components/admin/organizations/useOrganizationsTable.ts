"use client";

import { useState, useMemo, useCallback, useEffect } from "react";
import {
  useGetOrganizationsQuery,
  useCreateOrganizationMutation,
  useUpdateOrganizationMutation,
  useDeleteOrganizationMutation,
  Organization,
  createOrganizationFormData,
} from "@/store/api/organizationsApiSlice";
import type { OrganizationFormValues } from "@/lib/validations/organization";
import { useToast } from "@/hooks/use-toast";

type OrganizationFilters = {
  org_name?: string;
  org_type?: string;
  subscription_plan?: string;
};

export function useOrganizationsTable(filters?: OrganizationFilters) {
  const { toast } = useToast();

  // Pagination state
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  // Fetch organizations
  const {
    data: organizationsData,
    isLoading,
    isFetching,
    error,
    refetch,
  } = useGetOrganizationsQuery({
    page,
    per_page: pageSize,
    org_name: filters?.org_name,
    org_type: filters?.org_type,
    subscription_plan: filters?.subscription_plan,
  });

  useEffect(() => {
    setPage(1);
  }, [filters?.org_name, filters?.org_type, filters?.subscription_plan]);

  // Mutations
  const [createOrganization, { isLoading: isCreating }] =
    useCreateOrganizationMutation();
  const [updateOrganization, { isLoading: isUpdating }] =
    useUpdateOrganizationMutation();
  const [deleteOrganization, { isLoading: isDeleting }] =
    useDeleteOrganizationMutation();

  // Extract data
  const organizations = organizationsData?.data.organizations || [];
  const pagination = organizationsData?.data.pagination;

  // Handlers
  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
  }, []);

  const handlePageSizeChange = useCallback((newPageSize: number) => {
    setPageSize(newPageSize);
    setPage(1); // Reset to first page
  }, []);

  const handleRefresh = useCallback(async () => {
    await refetch();
    toast({
      title: "Refreshed",
      description: "Organizations list has been refreshed.",
    });
  }, [refetch, toast]);

  const handleView = useCallback((org: Organization) => {
    // View handler - to be implemented by page component
  }, []);

  const handleEdit = useCallback((org: Organization) => {
    // Edit handler - to be implemented by page component
  }, []);

  const handleDelete = useCallback(
    async (org: Organization) => {
      try {
        await deleteOrganization(org.id).unwrap();
        toast({
          title: "Success",
          description: `${org.org_name} has been deleted successfully.`,
        });
      } catch (error: any) {
        toast({
          title: "Error",
          description: error?.data?.message || "Failed to delete organization.",
          variant: "destructive",
        });
      }
    },
    [deleteOrganization, toast]
  );

  const handleCreate = useCallback(
    async (data: OrganizationFormValues) => {
      try {
        const formData = createOrganizationFormData(data);
        await createOrganization(formData).unwrap();

        toast({
          title: "Success",
          description: "Organization created successfully.",
        });
      } catch (error: any) {
        toast({
          title: "Error",
          description:
            error?.data?.message || "Failed to create organization.",
          variant: "destructive",
        });
        throw error; // Re-throw to let form handle it
      }
    },
    [createOrganization, toast]
  );

  const handleUpdate = useCallback(
    async (data: OrganizationFormValues & { id?: number }) => {
      if (!data.id) {
        toast({
          title: "Error",
          description: "Organization ID is required for update.",
          variant: "destructive",
        });
        return;
      }

      try {
        const formData = createOrganizationFormData(data, true);

        await updateOrganization({
          id: data.id,
          formData,
        }).unwrap();

        toast({
          title: "Success",
          description: "Organization updated successfully.",
        });
      } catch (error: any) {
        toast({
          title: "Error",
          description:
            error?.data?.message || "Failed to update organization.",
          variant: "destructive",
        });
        throw error;
      }
    },
    [updateOrganization, toast]
  );

  const handleExport = useCallback(
    async (format: "csv" | "excel" | "json" | "pdf") => {
      // Transform data for export
      const exportData = organizations.map((org) => ({
        "Organization Name": org.org_name,
        Type: org.org_type,
        "Subscription Plan": org.subscription_plan,
        Email: org.email,
        Employees: org.no_of_employees || "N/A",
        "Monthly Revenue": org.monthly_revenue
          ? `$${org.monthly_revenue.toLocaleString()}`
          : "N/A",
        "Yearly Revenue": org.yearly_revenue
          ? `$${org.yearly_revenue.toLocaleString()}`
          : "N/A",
        "Tax/BIN": org.tax_bin_no || "N/A",
        Created: new Date(org.created_at).toLocaleDateString(),
      }));

      // Dynamic import to reduce bundle size
      const { exportToCSV, exportToExcel, exportToJSON } =
        await import("@/components/global/table/utils/exportHelpers");

      const filename = `organizations_${new Date().toISOString().split("T")[0]}`;

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
        description: `Organizations exported as ${format.toUpperCase()}.`,
      });
    },
    [organizations, toast]
  );

  // Calculate stats
  const stats = useMemo(() => {
    const total = pagination?.total || 0;
    const plans = organizations.reduce((acc, org) => {
      acc[org.subscription_plan] = (acc[org.subscription_plan] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    return {
      total,
      premium: plans["Premium"] || 0,
      enterprise: plans["Enterprise"] || 0,
      basic: plans["Basic"] || 0 + plans["Free"] || 0,
    };
  }, [organizations, pagination?.total]);

  return {
    // Data
    organizations,
    pagination: pagination
      ? {
        page: pagination.current_page,
        pageSize: pagination.per_page,
        total: pagination.total,
      }
      : { page: 1, pageSize: 10, total: 0 },
    stats,

    // Loading states
    isLoading: isLoading || isFetching,
    isCreating,
    isUpdating,
    isDeleting,
    error,

    // Handlers
    handlePageChange,
    handlePageSizeChange,
    handleRefresh,
    handleView,
    handleEdit,
    handleDelete,
    handleCreate,
    handleUpdate,
    handleExport,
  };
}
