"use client";

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronDown, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { ColumnDef, SortingState } from "./types";
import { useState } from "react";

interface TableContentProps<TData> {
  data: TData[];
  columns: ColumnDef<TData>[];
  columnVisibility?: Record<string, boolean>;
  sorting?: SortingState[];
  onSortingChange?: (sorting: SortingState[]) => void;
  enableSorting?: boolean;
  enableRowSelection?: boolean;
  rowSelection?: Record<string, boolean>;
  onRowSelectionChange?: (selection: Record<string, boolean>) => void;
  rowKey?: keyof TData | ((row: TData) => string);
  onRowClick?: (row: TData) => void;
  rowClassName?: (row: TData) => string;
  expandableRows?: {
    renderExpanded: (row: TData) => React.ReactNode;
    isExpanded?: (row: TData) => boolean;
    onExpandedChange?: (row: TData, expanded: boolean) => void;
  };
}

export function TableContent<TData>({
  data,
  columns,
  columnVisibility = {},
  sorting = [],
  onSortingChange,
  enableSorting = true,
  enableRowSelection = false,
  rowSelection = {},
  onRowSelectionChange,
  rowKey = "id" as keyof TData,
  onRowClick,
  rowClassName,
  expandableRows,
}: TableContentProps<TData>) {
  const [expandedRows, setExpandedRows] = useState<Record<string, boolean>>({});

  // Get row key helper
  const getRowKey = (row: TData): string => {
    if (typeof rowKey === "function") {
      return rowKey(row);
    }
    return String(row[rowKey]);
  };

  // Filter visible columns
  const visibleColumns = columns.filter(
    (col) => columnVisibility[col.id] !== false
  );

  // Handle sorting
  const handleSort = (columnId: string) => {
    if (!enableSorting || !onSortingChange) return;

    const existingSort = sorting.find((s) => s.id === columnId);

    if (!existingSort) {
      onSortingChange([{ id: columnId, desc: false }]);
    } else if (!existingSort.desc) {
      onSortingChange([{ id: columnId, desc: true }]);
    } else {
      onSortingChange([]);
    }
  };

  // Get sort icon
  const getSortIcon = (columnId: string) => {
    const sort = sorting.find((s) => s.id === columnId);
    if (!sort) return <ArrowUpDown className="ml-2 h-4 w-4" />;
    return sort.desc ? (
      <ArrowDown className="ml-2 h-4 w-4" />
    ) : (
      <ArrowUp className="ml-2 h-4 w-4" />
    );
  };

  // Handle row selection
  const handleSelectAll = (checked: boolean) => {
    if (!onRowSelectionChange) return;

    if (checked) {
      const newSelection: Record<string, boolean> = {};
      data.forEach((row) => {
        newSelection[getRowKey(row)] = true;
      });
      onRowSelectionChange(newSelection);
    } else {
      onRowSelectionChange({});
    }
  };

  const handleSelectRow = (row: TData, checked: boolean) => {
    if (!onRowSelectionChange) return;

    const key = getRowKey(row);
    const newSelection = { ...rowSelection };

    if (checked) {
      newSelection[key] = true;
    } else {
      delete newSelection[key];
    }

    onRowSelectionChange(newSelection);
  };

  // Handle row expansion
  const handleToggleExpand = (row: TData) => {
    const key = getRowKey(row);
    const isCurrentlyExpanded = expandedRows[key] || false;

    setExpandedRows((prev) => ({
      ...prev,
      [key]: !isCurrentlyExpanded,
    }));

    expandableRows?.onExpandedChange?.(row, !isCurrentlyExpanded);
  };

  const isRowExpanded = (row: TData): boolean => {
    if (expandableRows?.isExpanded) {
      return expandableRows.isExpanded(row);
    }
    return expandedRows[getRowKey(row)] || false;
  };

  // Check if all rows are selected
  const isAllSelected =
    data.length > 0 && data.every((row) => rowSelection[getRowKey(row)]);
  const isSomeSelected =
    data.some((row) => rowSelection[getRowKey(row)]) && !isAllSelected;

  return (
    <Table className="w-full">
      <TableHeader className="bg-emerald-50/50 dark:bg-emerald-900/10">
          <TableRow className="hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 border-b border-emerald-100 dark:border-emerald-900/30">
            {/* Expandable column */}
            {expandableRows && (
              <TableHead className="w-[50px] min-w-[50px] h-12 px-4 font-semibold"></TableHead>
            )}

            {/* Selection column */}
            {enableRowSelection && (
              <TableHead className="w-[50px] min-w-[50px] h-12 px-4 font-semibold">
                <Checkbox
                  checked={isAllSelected}
                  onCheckedChange={handleSelectAll}
                  aria-label="Select all rows"
                  className={cn(
                    isSomeSelected && "data-[state=checked]:bg-emerald-600 dark:data-[state=checked]:bg-emerald-500",
                    "dark:border-emerald-800"
                  )}
                />
              </TableHead>
            )}

            {/* Data columns */}
            {visibleColumns.map((column) => {
              const canSort = column.enableSorting !== false && enableSorting;
              const headerContent =
                typeof column.header === "function"
                  ? column.header(column)
                  : column.header;

              return (
                <TableHead
                  key={column.id}
                  className={cn(
                    "h-12 px-4 font-semibold text-emerald-900 dark:text-slate-200 whitespace-nowrap",
                    column.meta?.headerClassName
                  )}
                  style={{ minWidth: column.meta?.minWidth || 'auto' }}
                >
                  {canSort && onSortingChange ? (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="-ml-3 h-8 font-semibold data-[state=open]:bg-emerald-100/50 dark:data-[state=open]:bg-emerald-900/20 hover:bg-emerald-100/50 dark:hover:bg-emerald-900/20 text-emerald-900 dark:text-slate-200 whitespace-nowrap"
                      onClick={() => handleSort(column.id)}
                    >
                      <span>{headerContent}</span>
                      {getSortIcon(column.id)}
                    </Button>
                  ) : (
                    headerContent
                  )}
                </TableHead>
              );
            })}
          </TableRow>
        </TableHeader>

        <TableBody>
          {data.map((row) => {
            const key = getRowKey(row);
            const isSelected = rowSelection[key] || false;
            const isExpanded = isRowExpanded(row);

            return (
              <>
                <TableRow
                  key={key}
                  data-state={isSelected ? "selected" : undefined}
                  className={cn(
                    "border-emerald-100 dark:border-emerald-900/30",
                    onRowClick && "cursor-pointer hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10",
                    rowClassName?.(row)
                  )}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                >
                  {/* Expandable toggle */}
                  {expandableRows && (
                    <TableCell className="px-4 py-3 w-[50px] min-w-[50px]">
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleToggleExpand(row);
                        }}
                      >
                        {isExpanded ? (
                          <ChevronDown className="h-4 w-4" />
                        ) : (
                          <ChevronRight className="h-4 w-4" />
                        )}
                      </Button>
                    </TableCell>
                  )}

                  {/* Selection checkbox */}
                  {enableRowSelection && (
                    <TableCell
                      className="px-4 py-3 w-[50px] min-w-[50px]"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <Checkbox
                        checked={isSelected}
                        onCheckedChange={(checked) =>
                          handleSelectRow(row, checked as boolean)
                        }
                        aria-label={`Select row ${key}`}
                      />
                    </TableCell>
                  )}

                  {/* Data cells */}
                  {visibleColumns.map((column) => {
                    const value = column.accessorKey
                      ? row[column.accessorKey as keyof TData]
                      : null;

                    const cellContent = column.cell
                      ? column.cell({ row: { original: row }, value, column })
                      : String(value ?? "");

                    return (
                      <TableCell
                        key={`${key}-${column.id}`}
                        className={cn("px-4 py-3 text-emerald-900 dark:text-white", column.meta?.className)}
                        style={{ minWidth: column.meta?.minWidth || 'auto' }}
                      >
                        {cellContent}
                      </TableCell>
                    );
                  })}
                </TableRow>

                {/* Expanded row content */}
                {expandableRows && isExpanded && (
                  <TableRow>
                    <TableCell
                      colSpan={
                        visibleColumns.length +
                        (enableRowSelection ? 1 : 0) +
                        1
                      }
                      className="bg-muted/50 p-4"
                    >
                      {expandableRows.renderExpanded(row)}
                    </TableCell>
                  </TableRow>
                )}
              </>
            );
          })}
        </TableBody>
    </Table>
  );
}
