"use client";

import { useMemo, forwardRef, useImperativeHandle, useState } from "react";
import { useColumnVisibility } from "@/components/global/table";
import { useOrganizationsTable } from "@/components/admin/organizations/useOrganizationsTable";
import { useOrganizationModal } from "@/components/admin/organizations/useOrganizationModal";
import { getOrganizationColumns } from "@/components/admin/organizations/organizationsTableConfig";
import {
  getEmptyState,
  getLoadingState,
} from "@/components/admin/organizations/organizationsPageConfig";
import { OrganizationModal } from "@/components/admin/organizations/OrganizationModal";
import { DataTable } from "@/components/global/table";
import { SearchFilter } from "@/components/ui/filters";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useGetOrganizationTypesQuery } from "@/store/api/organizationTypesApiSlice";
import { useGetSubscriptionPlansQuery } from "@/store/api/subscriptionPlansApiSlice";

export const OrganizationsTableTab = forwardRef<{ openModal: () => void }>(
  function OrganizationsTableTab(props, ref) {
    const [searchTerm, setSearchTerm] = useState("");
    const [orgTypeFilter, setOrgTypeFilter] = useState("");
    const [subscriptionPlanFilter, setSubscriptionPlanFilter] = useState("");

    // Data & API handlers
    const {
      organizations,
      pagination,
      isLoading,
      isCreating,
      isUpdating,
      error,
      handlePageChange,
      handlePageSizeChange,
      handleRefresh,
      handleDelete,
      handleCreate,
      handleUpdate,
    } = useOrganizationsTable({
      org_name: searchTerm,
      org_type: orgTypeFilter,
      subscription_plan: subscriptionPlanFilter,
    });

    const { data: orgTypesRes, isLoading: isLoadingOrgTypes } =
      useGetOrganizationTypesQuery({ page: 1, per_page: 100 });
    const { data: subscriptionPlansRes, isLoading: isLoadingPlans } =
      useGetSubscriptionPlansQuery({ page: 1, per_page: 100 });

    const orgTypeOptions = useMemo(
      () =>
        (orgTypesRes?.data?.organization_types ?? []).map((type) => ({
          value: type.type_name,
          label: type.type_name,
        })),
      [orgTypesRes?.data?.organization_types],
    );

    const subscriptionPlanOptions = useMemo(
      () =>
        (subscriptionPlansRes?.data?.subscription_plans ?? []).map((plan) => ({
          value: plan.plan_name,
          label: plan.plan_name,
        })),
      [subscriptionPlansRes?.data?.subscription_plans],
    );

    // Modal management
    const {
      modalState,
      apiErrors,
      errorMessage,
      openModal,
      closeModal,
      handleSubmit,
    } = useOrganizationModal(handleCreate, handleUpdate);

    // Expose openModal to parent via ref
    useImperativeHandle(ref, () => ({
      openModal: () => openModal("create"),
    }));

    // Table columns
    const columns = useMemo(
      () =>
        getOrganizationColumns({
          onView: (org) => openModal("view", org),
          onEdit: (org) => openModal("edit", org),
          onDelete: handleDelete,
        }),
      [openModal, handleDelete],
    );

    const { columnVisibility, setColumnVisibility } =
      useColumnVisibility(columns);

    // Page configurations
    const emptyState = useMemo(
      () => getEmptyState(() => openModal("create")),
      [openModal],
    );
    const loadingState = useMemo(() => getLoadingState(isLoading), [isLoading]);

    const customFilters = (
      <div className="flex flex-wrap gap-3">
        <div className="w-full sm:w-auto sm:min-w-[200px] sm:max-w-[300px]">
          <SearchFilter
            value={searchTerm || ""}
            onChange={(e) => setSearchTerm(e)}
            placeholder={"Search by organization name..."}
            size="sm"
          />
        </div>
        <Select
          value={orgTypeFilter || "__all__"}
          onValueChange={(value) =>
            setOrgTypeFilter(value === "__all__" ? "" : value)
          }
        >
          <SelectTrigger size="sm">
            <SelectValue placeholder="Organization type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">All types</SelectItem>
            {isLoadingOrgTypes ? (
              <SelectItem value="loading" disabled>
                Loading...
              </SelectItem>
            ) : (
              orgTypeOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))
            )}
          </SelectContent>
        </Select>
        <Select
          value={subscriptionPlanFilter || "__all__"}
          onValueChange={(value) =>
            setSubscriptionPlanFilter(value === "__all__" ? "" : value)
          }
        >
          <SelectTrigger size="sm">
            <SelectValue placeholder="Subscription plan" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">All plans</SelectItem>
            {isLoadingPlans ? (
              <SelectItem value="loading" disabled>
                Loading...
              </SelectItem>
            ) : (
              subscriptionPlanOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))
            )}
          </SelectContent>
        </Select>
      </div>
    );

    console.log(organizations, "organizations");

    return (
      <>
        <OrganizationModal
          open={modalState.open}
          onOpenChange={closeModal}
          mode={modalState.mode}
          organization={modalState.organization}
          onSubmit={handleSubmit}
          isLoading={modalState.mode === "create" ? isCreating : isUpdating}
          apiErrors={apiErrors}
          errorMessage={errorMessage}
        />

        <DataTable
          // Title & Description (inside table card)
          title="All Organizations"
          description="View and manage all registered organizations in the system"
          // Filters (inside table card)
          // searchValue={searchTerm}
          // onSearchChange={setSearchTerm}
          // searchPlaceholder="Search organizations..."
          // showDateRange={true}
          // dateRangeValue={dateRange}
          // onDateRangeChange={handleDateRangeChange}
          // dateRangePlaceholder="Filter by date range..."
          // Table Data
          data={organizations}
          columns={columns}
          columnVisibility={columnVisibility}
          onColumnVisibilityChange={setColumnVisibility}
          // Pagination
          pagination={pagination}
          paginationHandlers={{
            onPageChange: handlePageChange,
            onPageSizeChange: handlePageSizeChange,
          }}
          customFilters={customFilters}
          enablePagination
          // States
          loading={loadingState}
          emptyState={emptyState}
          errorState={{ error: error as Error, onRetry: handleRefresh }}
          // Row Config
          rowKey="id"
          rowClassName={(org) =>
            org.subscription_plan === "Enterprise" ? "bg-primary/5" : ""
          }
          // Actions
          onRefresh={handleRefresh}
          showRefreshButton={true}
          showColumnToggle={true}
          ariaLabel="Organizations table"
        />
      </>
    );
  },
);
