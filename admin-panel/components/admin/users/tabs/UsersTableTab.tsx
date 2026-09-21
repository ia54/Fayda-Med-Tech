"use client";

import { forwardRef, useImperativeHandle, useMemo, useState } from "react";
import { DataTable } from "@/components/global/table";
import { UserModal } from "../UserModal";
import { useUsersTable } from "../useUsersTable";
import { useUserModal } from "../useUserModal";
import { useUserFilters } from "../useUserFilters";
import { getUserColumns } from "../usersTableConfig";
import { User } from "@/store/api/usersApiSlice";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Filter } from "lucide-react";
import { ROLE_LABELS, UserRoleEnum } from "@/lib/validations/user";
import { Button } from "@/components/ui/button";

export interface UsersTableTabRef {
  openModal: () => void;
}

export const UsersTableTab = forwardRef<UsersTableTabRef>((props, ref) => {
  const dispatch = useAppDispatch();

  // Hooks
  const filters = useUserFilters();

  const tableData = useUsersTable(filters);
  const modal = useUserModal(tableData.handleCreate, tableData.handleUpdate);

  // Column visibility state
  const [columnVisibility, setColumnVisibility] = useState<
    Record<string, boolean>
  >({});

  // Expose openModal to parent
  useImperativeHandle(ref, () => ({
    openModal: () => modal.openModal("create"),
  }));

  // Delete handler with confirmation
  const handleDelete = (user: User) => {
    const userName =
      user?.first_name && user?.last_name
        ? `${user.first_name} ${user.last_name}`
        : user?.email || "this user";

    dispatch(
      openModal({
        type: "confirm",
        props: {
          title: "Delete User",
          message: `Are you sure you want to delete "${userName}"? This action cannot be undone.`,
          confirmText: "Delete",
          cancelText: "Cancel",
          variant: "destructive",
          onConfirm: async () => {
            await tableData.handleDelete(user);
          },
        },
      })
    );
  };

  // Table columns
  const columns = useMemo(
    () =>
      getUserColumns({
        onView: (user) => modal.openModal("view", user),
        onEdit: (user) => modal.openModal("edit", user),
        onDelete: handleDelete,
      }),
    [modal.openModal, handleDelete]
  );

  // Custom filters component
  const customFilters = useMemo(
    () => (
      <>
        {/* Role Filter */}
        <div className="w-full sm:w-auto sm:min-w-[180px]">
          <Select
            value={filters.roleFilter}
            onValueChange={(value) =>
              filters.setRoleFilter(value as typeof filters.roleFilter)
            }
          >
            <SelectTrigger className="h-9">
              <SelectValue placeholder="Filter by role" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Roles</SelectItem>
              {UserRoleEnum.options.map((role) => (
                <SelectItem key={role} value={role}>
                  {ROLE_LABELS[role]}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Status Filter */}
        <div className="w-full sm:w-auto sm:min-w-[180px]">
          <Select
            value={filters.statusFilter}
            onValueChange={(value) =>
              filters.setStatusFilter(value as typeof filters.statusFilter)
            }
          >
            <SelectTrigger className="h-9">
              <SelectValue placeholder="Filter by status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Reset Filters */}
        {(filters.searchTerm ||
          filters.roleFilter !== "all" ||
          filters.statusFilter !== "all") && (
          <Button
            size="sm"
            variant="outline"
            onClick={filters.resetFilters}
            className="h-9"
          >
            <Filter className="mr-2 h-4 w-4" />
            Reset
          </Button>
        )}
      </>
    ),
    [filters]
  );

  // Refresh handler
  const handleRefresh = () => {
    tableData.refetch();
  };

  return (
    <>
      {/* Data Table */}
      <DataTable
        title="All Users"
        description="View and manage all registered users in the system"
        // Table Data
        columns={columns}
        data={tableData.users}
        // Search
        searchValue={filters.searchTerm}
        onSearchChange={filters.setSearchTerm}
        searchPlaceholder="Search users by name, email, or organization..."
        // Date Range Filter
        // showDateRange={true}
        // dateRangeValue={filters.dateRange}
        // onDateRangeChange={filters.handleDateRangeChange}
        // dateRangePlaceholder="Filter by date range..."
        // Custom Filters
        customFilters={customFilters}
        // Pagination
        pagination={{
          page: tableData.currentPage,
          pageSize: tableData.pageSize,
          total: tableData.totalUsers,
          pageSizeOptions: [10, 25, 50, 100],
        }}
        paginationHandlers={{
          onPageChange: tableData.setCurrentPage,
          onPageSizeChange: tableData.setPageSize,
        }}
        enablePagination
        // Column Visibility
        columnVisibility={columnVisibility}
        onColumnVisibilityChange={setColumnVisibility}
        // Refresh
        onRefresh={handleRefresh}
        showRefreshButton={true}
        showColumnToggle={true}
        // States
        loading={{ isLoading: tableData.isLoading || tableData.isFetching }}
        // Row Config
        rowKey="id"
      />

      {/* User Modal */}
      <UserModal
        open={modal.isOpen}
        onOpenChange={(open) => !open && modal.closeModal()}
        mode={modal.mode}
        user={modal.selectedUser}
        onSubmit={modal.handleSubmit}
        isLoading={tableData.isCreating || tableData.isUpdating}
        apiErrors={modal.apiErrors}
      />
    </>
  );
});

UsersTableTab.displayName = "UsersTableTab";
