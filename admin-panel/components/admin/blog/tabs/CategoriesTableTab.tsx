"use client";

import { useMemo, forwardRef, useImperativeHandle } from "react";
import { useColumnVisibility } from "@/components/global/table";
import { useCategoriesTable } from "@/components/admin/blog/blogCategories/useCategoriesTable";
import { useCategoryModal } from "@/components/admin/blog/blogCategories/useCategoryModal";
import { getCategoryColumns } from "@/components/admin/blog/blogCategories/categoriesTableConfig";
import { CategoryModal } from "@/components/admin/blog/blogCategories/CategoryModal";
import { DataTable } from "@/components/global/table";
import { FileText } from "lucide-react";

export const CategoriesTableTab = forwardRef<{ openModal: () => void }>(
  function CategoriesTableTab(props, ref) {
    // Data & API handlers
    const {
      categories,
      pagination,
      isLoading,
      isCreating,
      isUpdating,
      error,
      handlePageChange,
      handlePageSizeChange,
      handleCreate,
      handleUpdate,
      handleDelete,
      handleRefresh,
    } = useCategoriesTable();

    // Modal management
    const {
      modalState,
      apiErrors,
      errorMessage,
      openModal,
      closeModal,
      handleSubmit,
    } = useCategoryModal(handleCreate, handleUpdate);

    // Expose openModal to parent via ref
    useImperativeHandle(ref, () => ({
      openModal: () => openModal("create"),
    }));

    // Table columns
    const columns = useMemo(
      () =>
        getCategoryColumns({
          onEdit: (category) => openModal("edit", category),
          onDelete: handleDelete,
        }),
      [openModal, handleDelete]
    );

    const { columnVisibility, setColumnVisibility } =
      useColumnVisibility(columns);

    // Empty state configuration
    const emptyState = useMemo(
      () => ({
        title: "No categories found",
        description: "Get started by creating your first blog category",
        action: {
          label: "Create Category",
          onClick: () => openModal("create"),
        },
      }),
      [openModal]
    );

    // Loading state configuration
    const loadingState = useMemo(
      () => ({
        isLoading,
        message: "Loading categories...",
        rows: 5,
      }),
      [isLoading]
    );

    return (
      <>
        <CategoryModal
          open={modalState.open}
          onOpenChange={closeModal}
          mode={modalState.mode}
          category={modalState.category}
          onSubmit={handleSubmit}
          isLoading={modalState.mode === "create" ? isCreating : isUpdating}
          apiErrors={apiErrors}
          errorMessage={errorMessage}
        />

        <DataTable
          // Title & Description (inside table card)
          title="Blog Categories"
          description="Manage blog post categories"
          // Table Data
          data={categories}
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
          loading={loadingState}
          emptyState={emptyState}
          errorState={{ error: error as Error, onRetry: handleRefresh }}
          // Row Config
          rowKey="id"
          // Toolbar Actions
          addNewButton={{
            label: "Add Category",
            onClick: () => openModal("create"),
          }}
          onRefresh={handleRefresh}
          showRefreshButton={true}
          showColumnToggle={true}
          ariaLabel="Blog categories table"
        />
      </>
    );
  }
);
