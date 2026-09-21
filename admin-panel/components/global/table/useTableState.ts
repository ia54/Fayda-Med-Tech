"use client";

import { useState, useCallback, useMemo } from "react";
import {
  SortingState,
  ColumnVisibility,
  PaginationConfig,
  TableState,
} from "./types";

interface UseTableStateProps<TData> {
  data: TData[];
  initialPagination?: Partial<PaginationConfig>;
  initialSorting?: SortingState[];
  initialColumnVisibility?: ColumnVisibility;
  rowKey?: keyof TData | ((row: TData) => string);
}

export function useTableState<TData = any>({
  data,
  initialPagination = {},
  initialSorting = [],
  initialColumnVisibility = {},
  rowKey = "id" as keyof TData,
}: UseTableStateProps<TData>): TableState<TData> {
  // Sorting State
  const [sorting, setSorting] = useState<SortingState[]>(initialSorting);

  // Column Visibility State
  const [columnVisibility, setColumnVisibility] =
    useState<ColumnVisibility>(initialColumnVisibility);

  // Row Selection State
  const [rowSelection, setRowSelection] = useState<Record<string, boolean>>({});

  // Pagination State
  const [pagination, setPaginationState] = useState<PaginationConfig>({
    page: initialPagination.page || 1,
    pageSize: initialPagination.pageSize || 10,
    total: initialPagination.total || data.length,
    pageSizeOptions: initialPagination.pageSizeOptions || [10, 20, 30, 50, 100],
  });

  // Update pagination handler
  const setPagination = useCallback((updates: Partial<PaginationConfig>) => {
    setPaginationState((prev) => ({ ...prev, ...updates }));
  }, []);

  // Get row key helper
  const getRowKey = useCallback(
    (row: TData): string => {
      if (typeof rowKey === "function") {
        return rowKey(row);
      }
      return String(row[rowKey]);
    },
    [rowKey]
  );

  // Get selected rows
  const getSelectedRows = useCallback((): TData[] => {
    return data.filter((row) => {
      const key = getRowKey(row);
      return rowSelection[key];
    });
  }, [data, rowSelection, getRowKey]);

  // Reset selection
  const resetSelection = useCallback(() => {
    setRowSelection({});
  }, []);

  // Reset filters (can be extended)
  const resetFilters = useCallback(() => {
    setSorting([]);
    setRowSelection({});
    setPaginationState((prev) => ({
      ...prev,
      page: 1,
    }));
  }, []);

  // Memoized state object
  const state: TableState<TData> = useMemo(
    () => ({
      data,
      sorting,
      columnVisibility,
      rowSelection,
      pagination,
      setSorting,
      setColumnVisibility,
      setRowSelection,
      setPagination,
      getSelectedRows,
      resetSelection,
      resetFilters,
    }),
    [
      data,
      sorting,
      columnVisibility,
      rowSelection,
      pagination,
      getSelectedRows,
      resetSelection,
      resetFilters,
      setPagination,
    ]
  );

  return state;
}

// Helper hook for managing column visibility
export function useColumnVisibility(columns: Array<{ id: string }>) {
  const [columnVisibility, setColumnVisibility] = useState<ColumnVisibility>(
    () => {
      const initial: ColumnVisibility = {};
      columns.forEach((col) => {
        initial[col.id] = true;
      });
      return initial;
    }
  );

  const toggleColumn = useCallback((columnId: string) => {
    setColumnVisibility((prev) => ({
      ...prev,
      [columnId]: !prev[columnId],
    }));
  }, []);

  const showAllColumns = useCallback(() => {
    const all: ColumnVisibility = {};
    columns.forEach((col) => {
      all[col.id] = true;
    });
    setColumnVisibility(all);
  }, [columns]);

  const hideAllColumns = useCallback(() => {
    const none: ColumnVisibility = {};
    columns.forEach((col) => {
      none[col.id] = false;
    });
    setColumnVisibility(none);
  }, [columns]);

  return {
    columnVisibility,
    setColumnVisibility,
    toggleColumn,
    showAllColumns,
    hideAllColumns,
  };
}

// Helper hook for sorting
export function useSorting(
  initialSorting: SortingState[] = [],
  onSortingChange?: (sorting: SortingState[]) => void
) {
  const [sorting, setSortingState] = useState<SortingState[]>(initialSorting);

  const setSorting = useCallback(
    (newSorting: SortingState[] | ((prev: SortingState[]) => SortingState[])) => {
      const updated = typeof newSorting === 'function' ? newSorting(sorting) : newSorting;
      setSortingState(updated);
      onSortingChange?.(updated);
    },
    [onSortingChange, sorting]
  );

  const toggleSort = useCallback(
    (columnId: string) => {
      setSorting((prev: SortingState[]) => {
        const existing = prev.find((s: SortingState) => s.id === columnId);
        if (!existing) {
          return [{ id: columnId, desc: false }];
        }
        if (!existing.desc) {
          return [{ id: columnId, desc: true }];
        }
        return [];
      });
    },
    [setSorting]
  );

  const clearSorting = useCallback(() => {
    setSorting([]);
  }, [setSorting]);

  return {
    sorting,
    setSorting,
    toggleSort,
    clearSorting,
  };
}