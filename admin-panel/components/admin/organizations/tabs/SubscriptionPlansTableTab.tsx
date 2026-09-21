"use client";

import { useMemo } from "react";
import { useColumnVisibility } from "@/components/global/table";
import { DataTable } from "@/components/global/table";
import { Plus } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useSubscriptionPlansTable } from "../subscriptionPlans/useSubscriptionPlansTable";
import { useSubscriptionPlanModal } from "../subscriptionPlans/useSubscriptionPlanModal";
import { getSubscriptionPlansColumns } from "../subscriptionPlans/subscriptionPlansTableConfig";
import { SubscriptionPlanModal } from "../subscriptionPlans/SubscriptionPlanModal";

export function SubscriptionPlansTableTab() {
  // Data & API handlers
  const {
    subscriptionPlans,
    pagination,
    statusFilter,
    isLoading,
    isCreating,
    isUpdating,
    error,
    handlePageChange,
    handlePageSizeChange,
    handleStatusFilterChange,
    handleRefresh,
    handleCreate,
    handleUpdate,
    handleDelete,
  } = useSubscriptionPlansTable();

  // Modal management
  const {
    modalState,
    apiErrors,
    errorMessage,
    openModal,
    closeModal,
    handleSubmit,
  } = useSubscriptionPlanModal(handleCreate, handleUpdate);

  // Table columns
  const columns = useMemo(
    () =>
      getSubscriptionPlansColumns({
        onView: (plan) => openModal("view", plan),
        onEdit: (plan) => openModal("edit", plan),
        onDelete: handleDelete,
      }),
    [openModal, handleDelete],
  );

  const { columnVisibility, setColumnVisibility } =
    useColumnVisibility(columns);

  // Custom Status Filter
  const statusFilterComponent = (
    <Select
      value={statusFilter || "all"}
      onValueChange={(value) =>
        handleStatusFilterChange(
          value === "all" ? undefined : (value as "active" | "inactive"),
        )
      }
    >
      <SelectTrigger className="w-[150px] h-9">
        <SelectValue placeholder="Status" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="all">All Status</SelectItem>
        <SelectItem value="active">Active</SelectItem>
        <SelectItem value="inactive">Inactive</SelectItem>
      </SelectContent>
    </Select>
  );

  return (
    <>
      {/* Subscription Plan Modal */}
      <SubscriptionPlanModal
        open={modalState.open}
        onOpenChange={closeModal}
        mode={modalState.mode}
        subscriptionPlan={modalState.subscriptionPlan}
        onSubmit={handleSubmit}
        isLoading={modalState.mode === "create" ? isCreating : isUpdating}
        apiErrors={apiErrors}
        errorMessage={errorMessage}
      />

      <DataTable
        // Title & Description
        title="Subscription Plans"
        description="Manage subscription tiers and pricing for organizations"
        // Table Data
        data={subscriptionPlans}
        columns={columns}
        columnVisibility={columnVisibility}
        onColumnVisibilityChange={setColumnVisibility}
        // Custom Filters
        customFilters={statusFilterComponent}
        // Pagination
        pagination={pagination}
        paginationHandlers={{
          onPageChange: handlePageChange,
          onPageSizeChange: handlePageSizeChange,
        }}
        enablePagination
        // States
        loading={{ isLoading, rowCount: 4 }}
        emptyState={{
          title: "No subscription plans found",
          description: "Get started by adding your first subscription plan.",
          action: {
            label: "Add First Plan",
            onClick: () => openModal("create"),
          },
        }}
        errorState={{ error: error as Error, onRetry: handleRefresh }}
        // Row Config
        rowKey="id"
        // Actions
        addNewButton={{
          label: "Add Plan",
          onClick: () => openModal("create"),
          icon: <Plus className="h-4 w-4" />,
        }}
        onRefresh={handleRefresh}
        showRefreshButton={true}
        showColumnToggle={true}
        ariaLabel="Subscription plans table"
      />
    </>
  );
}
