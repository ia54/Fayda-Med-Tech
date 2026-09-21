"use client";

import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { DataTableProps } from "./types";
import { BulkActions } from "./TableActions";
import { TableContent } from "./TableContent";
import { Pagination } from "./Pagination";
import {
  TableErrorBoundary,
  TableLoading,
  EmptyState,
  ErrorState,
} from "./TableStates";
import { Table, TableBody } from "@/components/ui/table";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
  DropdownMenuCheckboxItem,
} from "@/components/ui/antd-dropdown";
import { RefreshCw, Columns3 } from "lucide-react";
import { SearchFilter, DateRangeFilter } from "@/components/ui/filters";

export function DataTable<TData = any>({
  // Core Data
  data,
  columns,

  // Table Identity
  name,
  className,

  // Title & Description
  title,
  description,

  // Filters
  searchValue,
  onSearchChange,
  searchPlaceholder,
  showDateRange,
  dateRangeValue,
  onDateRangeChange,
  dateRangePlaceholder,
  customFilters,

  // Pagination
  pagination,
  paginationHandlers,
  enablePagination = true,

  // Sorting
  sorting,
  onSortingChange,
  enableSorting = true,

  // Column Visibility
  columnVisibility,
  onColumnVisibilityChange,

  // Loading & Error States
  loading,
  emptyState,
  errorState,

  // Row Configuration
  rowKey = "id" as keyof TData,
  onRowClick,
  rowClassName,

  // Selection
  enableRowSelection = false,
  rowSelection,
  onRowSelectionChange,

  // Bulk Actions
  bulkActions,

  // Advanced Features
  expandableRows,

  // Toolbar Actions
  addNewButton,
  onRefresh,
  showRefreshButton = true,
  showColumnToggle = true,

  // Accessibility
  ariaLabel,
}: DataTableProps<TData>) {
  // Calculate visible columns
  const visibleColumns = columns.filter(
    (col) => (columnVisibility?.[col.id] ?? true) !== false,
  );

  const columnCount =
    visibleColumns.length +
    (enableRowSelection ? 1 : 0) +
    (expandableRows ? 1 : 0);

  // Get selected rows
  const getRowKey = (row: TData): string => {
    if (typeof rowKey === "function") {
      return rowKey(row);
    }
    return String(row[rowKey]);
  };

  const selectedRows = data.filter((row) => {
    const key = getRowKey(row);
    return rowSelection?.[key];
  });

  const bulkActionItems = bulkActions?.(selectedRows) || [];

  // Determine what to render
  const hasError = errorState?.error;
  const isLoading = loading?.isLoading;
  const isEmpty = !isLoading && !hasError && data.length === 0;

  return (
    <TableErrorBoundary>
      <div className={cn("space-y-4 data-table-container w-full ", className)}>
        {/* Bulk Actions Bar (shown when rows selected) */}
        {enableRowSelection && selectedRows.length > 0 && (
          <Card className="bg-primary/5 border-primary/20">
            <CardContent className="p-4">
              <BulkActions
                selectedCount={selectedRows.length}
                actions={bulkActionItems}
                onClearSelection={() => onRowSelectionChange?.({})}
              />
            </CardContent>
          </Card>
        )}

        {/* Table Section (WITH CARD) */}
        <Card className="w-full max-w-full shadow-sm overflow-hidden bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50">
          <CardContent className="p-0 w-full max-w-full">
            {/* Header Section: Title, Filters, and Action Buttons */}
            {(title ||
              description ||
              onSearchChange ||
              showDateRange ||
              customFilters ||
              showRefreshButton ||
              showColumnToggle) && (
              <div className="p-4 border-b border-emerald-100 dark:border-emerald-900/50 space-y-4">
                {/* Title & Description */}
                {(title || description) && (
                  <div>
                    {title && (
                      <h2 className="text-xl md:text-2xl font-bold tracking-tight text-emerald-900 dark:text-white">
                        {title}
                      </h2>
                    )}
                    {description && (
                      <p className="text-sm text-emerald-600 dark:text-slate-300 mt-1">
                        {description}
                      </p>
                    )}
                  </div>
                )}

                {/* Filters and Actions Row */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                  {/* Left: Filters */}
                  <div className="flex-1 flex flex-wrap items-center gap-2">
                    {/* Date Range Filter */}
                    {showDateRange && onDateRangeChange && (
                      <div className="w-full sm:w-auto">
                        <DateRangeFilter
                          value={dateRangeValue}
                          onChange={onDateRangeChange}
                          placeholder={
                            dateRangePlaceholder || "Select date range..."
                          }
                          size="sm"
                          showPresets={true}
                          clearable={true}
                        />
                      </div>
                    )}

                    {/* Search Filter */}
                    {onSearchChange && (
                      <div className="w-full sm:w-auto sm:min-w-[200px] sm:max-w-[300px]">
                        <SearchFilter
                          value={searchValue || ""}
                          onChange={onSearchChange}
                          placeholder={searchPlaceholder || "Search..."}
                          size="sm"
                        />
                      </div>
                    )}

                    {/* Custom Filters */}
                    {customFilters}
                  </div>

                  {/* Right: Add New, Refresh & Column Toggle */}
                  <div className="flex items-center gap-2 shrink-0">
                    {/* Add New Button */}
                    {addNewButton && (
                      <Button
                        size="sm"
                        variant="default"
                        className="gap-2 h-9 cursor-pointer"
                        onClick={addNewButton.onClick}
                      >
                        {addNewButton.icon}
                        <span className="hidden sm:inline">
                          {addNewButton.label || "Add New"}
                        </span>
                      </Button>
                    )}

                    {/* Refresh Button */}
                    {showRefreshButton && onRefresh && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="gap-2 h-9 cursor-pointer"
                        onClick={onRefresh}
                      >
                        <RefreshCw className="h-4 w-4" />
                        <span className="hidden sm:inline">Refresh</span>
                      </Button>
                    )}

                    {/* Column Toggle */}
                    {showColumnToggle &&
                      columnVisibility !== undefined &&
                      onColumnVisibilityChange && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button
                              size="sm"
                              variant="outline"
                              className="gap-2 h-9 cursor-pointer"
                            >
                              <Columns3 className="h-4 w-4" />
                              <span className="hidden sm:inline">Columns</span>
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="w-48">
                            {columns.map((column) => (
                              <DropdownMenuCheckboxItem
                                key={column.id}
                                checked={
                                  columnVisibility?.[column.id] !== false
                                }
                                onCheckedChange={(checked: boolean) =>
                                  onColumnVisibilityChange({
                                    ...columnVisibility,
                                    [column.id]: checked,
                                  })
                                }
                                className="cursor-pointer"
                              >
                                {typeof column.header === "string"
                                  ? column.header
                                  : column.id}
                              </DropdownMenuCheckboxItem>
                            ))}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                  </div>
                </div>
              </div>
            )}

            <div className="overflow-x-auto">
              {hasError ? (
                // Error State
                <Table className="w-full">
                  <TableBody>
                    <ErrorState
                      config={errorState!}
                      columnCount={columnCount}
                    />
                  </TableBody>
                </Table>
              ) : isLoading ? (
                // Loading State
                <Table className="w-full">
                  <TableBody>
                    <TableLoading config={loading!} columnCount={columnCount} />
                  </TableBody>
                </Table>
              ) : isEmpty ? (
                // Empty State
                <Table className="w-full">
                  <TableBody>
                    <EmptyState config={emptyState} columnCount={columnCount} />
                  </TableBody>
                </Table>
              ) : (
                // Data Table - Horizontally scrollable on mobile
                <TableContent
                  data={data}
                  columns={columns}
                  columnVisibility={columnVisibility}
                  sorting={sorting}
                  onSortingChange={onSortingChange}
                  enableSorting={enableSorting}
                  enableRowSelection={enableRowSelection}
                  rowSelection={rowSelection}
                  onRowSelectionChange={onRowSelectionChange}
                  rowKey={rowKey}
                  onRowClick={onRowClick}
                  rowClassName={rowClassName}
                  expandableRows={expandableRows}
                />
              )}
            </div>

            {/* Pagination */}
            {enablePagination &&
              pagination &&
              paginationHandlers &&
              !isEmpty &&
              !isLoading &&
              !hasError && (
                <div className="border-t border-border pb-[140px]f">
                  <Pagination
                    pagination={pagination}
                    handlers={paginationHandlers}
                  />
                </div>
              )}
          </CardContent>
        </Card>
      </div>
    </TableErrorBoundary>
  );
}
