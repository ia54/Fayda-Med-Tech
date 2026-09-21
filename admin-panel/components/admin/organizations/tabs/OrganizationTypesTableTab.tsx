"use client";

import { useMemo } from "react";
import { useColumnVisibility } from "@/components/global/table";
import { DataTable } from "@/components/global/table";
import { Plus } from "lucide-react";
import { useOrganizationTypesTable } from "../organizationTypes/useOrganizationTypesTable";
import { useOrganizationTypeModal } from "../organizationTypes/useOrganizationTypeModal";
import { getOrganizationTypesColumns } from "../organizationTypes/organizationTypesTableConfig";
import { OrganizationTypeModal } from "../organizationTypes/OrganizationTypeModal";

export function OrganizationTypesTableTab() {
  // Data & API handlers
  const {
    organizationTypes,
    pagination,
    isLoading,
    isCreating,
    isUpdating,
    error,
    handlePageChange,
    handlePageSizeChange,
    handleRefresh,
    handleCreate,
    handleUpdate,
    handleDelete,
  } = useOrganizationTypesTable();

  // Modal management
  const {
    modalState,
    apiErrors,
    errorMessage,
    openModal,
    closeModal,
    handleSubmit,
  } = useOrganizationTypeModal(handleCreate, handleUpdate);

  // Table columns
  const columns = useMemo(
    () =>
      getOrganizationTypesColumns({
        onView: (orgType) => openModal("view", orgType),
        onEdit: (orgType) => openModal("edit", orgType),
        onDelete: handleDelete,
      }),
    [openModal, handleDelete]
  );

  const { columnVisibility, setColumnVisibility } =
    useColumnVisibility(columns);

  return (
    <>
      {/* Organization Type Modal */}
      <OrganizationTypeModal
        open={modalState.open}
        onOpenChange={closeModal}
        mode={modalState.mode}
        organizationType={modalState.organizationType}
        onSubmit={handleSubmit}
        isLoading={modalState.mode === "create" ? isCreating : isUpdating}
        apiErrors={apiErrors}
        errorMessage={errorMessage}
      />

      <DataTable
        // Title & Description
        title="Organization Types"
        description="Manage different types of organizations in the system"
        // Table Data
        data={organizationTypes}
        columns={columns}
        columnVisibility={columnVisibility}
        onColumnVisibilityChange={setColumnVisibility}
        // Pagination
        pagination={pagination}
        paginationHandlers={{
          onPageChange: handlePageChange,
          onPageSizeChange: handlePageSizeChange,
        }}
        enablePagination
        // States
        loading={{ isLoading, rowCount: 5 }}
        emptyState={{
          title: "No organization types found",
          description: "Get started by adding your first organization type.",
          action: {
            label: "Add First Type",
            onClick: () => openModal("create"),
          },
        }}
        errorState={{ error: error as Error, onRetry: handleRefresh }}
        // Row Config
        rowKey="id"
        // Actions
        addNewButton={{
          label: "Add Type",
          onClick: () => openModal("create"),
          icon: <Plus className="h-4 w-4" />,
        }}
        onRefresh={handleRefresh}
        showRefreshButton={true}
        showColumnToggle={true}
        ariaLabel="Organization types table"
      />
    </>
  );
}
