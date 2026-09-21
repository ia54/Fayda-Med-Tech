import { ReactNode } from "react";

// Column Definition
export interface ColumnDef<TData = any> {
  id: string;
  accessorKey?: keyof TData | string;
  header: string | ((column: ColumnDef<TData>) => ReactNode);
  cell?: (props: CellContext<TData>) => ReactNode;
  enableSorting?: boolean;
  enableHiding?: boolean;
  sortingFn?: (rowA: TData, rowB: TData) => number;
  meta?: {
    className?: string;
    headerClassName?: string;
    minWidth?: string;
    maxWidth?: string;
    width?: string;
  };
}

// Cell Context for custom cell renderers
export interface CellContext<TData = any> {
  row: {
    original: TData;
  };
  value: any;
  column: ColumnDef<TData>;
}

// Sorting State
export interface SortingState {
  id: string;
  desc: boolean;
}

// Pagination Configuration
export interface PaginationConfig {
  page: number;
  pageSize: number;
  total: number;
  pageSizeOptions?: number[];
}

// Backend Pagination Handlers
export interface PaginationHandlers {
  onPageChange: (page: number) => void;
  onPageSizeChange: (pageSize: number) => void;
  onSort?: (sorting: SortingState[]) => void;
}

// Column Visibility State
export type ColumnVisibility = Record<string, boolean>;

// Table Actions (Top Left)
export interface TableAction {
  label: string;
  icon?: ReactNode;
  onClick: () => void;
  variant?: "default" | "outline" | "ghost" | "destructive";
  disabled?: boolean;
  hidden?: boolean;
}

// Export Configuration
export interface ExportConfig {
  onExport: (format: ExportFormat) => void | Promise<void>;
  formats?: ExportFormat[];
  isExporting?: boolean;
}

export type ExportFormat = "csv" | "excel" | "pdf" | "json";

// Toolbar Configuration (Top Right)
export interface ToolbarConfig {
  showColumnToggle?: boolean;
  showRefresh?: boolean;
  customActions?: ReactNode;
}

// Filter Section Props
export interface FilterSectionProps {
  children?: ReactNode;
  className?: string;
}

// Card Stats Configuration
export interface CardStatsConfig {
  stats: StatCard[];
  className?: string;
}

export interface StatCard {
  id: string;
  title: string;
  value: string | number;
  icon?: ReactNode;
  description?: string;
  trend?: {
    value: number;
    isPositive: boolean;
  };
  onClick?: () => void;
}

// Loading State Configuration
export interface LoadingConfig {
  isLoading: boolean;
  rowCount?: number;
  message?: string;
}

// Empty State Configuration
export interface EmptyStateConfig {
  icon?: ReactNode;
  title?: string;
  description?: string;
  action?: {
    label: string;
    onClick: () => void;
  };
}

// Error State Configuration
export interface ErrorStateConfig {
  error: Error | null;
  onRetry?: () => void;
}

// Page Header Configuration
export interface PageHeaderConfig {
  title: string;
  description?: string;
  actions?: TableAction[];
  exportConfig?: ExportConfig;
}

// Date Range for Filtering
export interface DateRange {
  from?: Date;
  to?: Date;
}

// Filter Bar Configuration
export interface FilterBarConfig {
  // Search Filter
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  searchPlaceholder?: string;

  // Date Range Filter
  showDateRange?: boolean;
  dateRangeValue?: DateRange;
  onDateRangeChange?: (range: DateRange) => void;
  dateRangePlaceholder?: string;

  // Action Buttons
  showRefresh?: boolean;
  onRefresh?: () => void | Promise<void>;
  showColumnToggle?: boolean;

  // Custom Filters
  customFilters?: ReactNode;
}

// Main DataTable Props (Simplified - only handles table, refresh/columns buttons, and pagination)
export interface DataTableProps<TData = any> {
  // Core Data
  data: TData[];
  columns: ColumnDef<TData>[];

  // Table Identity
  name?: string;
  className?: string;

  // Title & Description (displayed at top left inside table card)
  title?: string;
  description?: string;

  // Filters (displayed at top left inside table card)
  searchValue?: string;
  onSearchChange?: (value: string) => void;
  searchPlaceholder?: string;
  showDateRange?: boolean;
  dateRangeValue?: DateRange;
  onDateRangeChange?: (range: DateRange) => void;
  dateRangePlaceholder?: string;
  customFilters?: ReactNode;

  // Pagination
  pagination?: PaginationConfig;
  paginationHandlers?: PaginationHandlers;
  enablePagination?: boolean;

  // Sorting
  sorting?: SortingState[];
  onSortingChange?: (sorting: SortingState[]) => void;
  enableSorting?: boolean;

  // Column Visibility
  columnVisibility?: ColumnVisibility;
  onColumnVisibilityChange?: (visibility: ColumnVisibility) => void;

  // Loading & Error States
  loading?: LoadingConfig;
  emptyState?: EmptyStateConfig;
  errorState?: ErrorStateConfig;

  // Row Configuration
  rowKey?: keyof TData | ((row: TData) => string);
  onRowClick?: (row: TData) => void;
  rowClassName?: (row: TData) => string;

  // Selection
  enableRowSelection?: boolean;
  rowSelection?: Record<string, boolean>;
  onRowSelectionChange?: (selection: Record<string, boolean>) => void;

  // Bulk Actions (shown when rows selected)
  bulkActions?: (selectedRows: TData[]) => TableAction[];

  // Advanced Features
  expandableRows?: {
    renderExpanded: (row: TData) => ReactNode;
    isExpanded?: (row: TData) => boolean;
    onExpandedChange?: (row: TData, expanded: boolean) => void;
  };

  // Toolbar Actions (Add New, Refresh & Column Toggle)
  addNewButton?: {
    label?: string;
    onClick: () => void;
    icon?: ReactNode;
  };
  onRefresh?: () => void | Promise<void>;
  showRefreshButton?: boolean;
  showColumnToggle?: boolean;

  // Accessibility
  ariaLabel?: string;
}

// Table State Hook Return Type
export interface TableState<TData = any> {
  data: TData[];
  sorting: SortingState[];
  columnVisibility: ColumnVisibility;
  rowSelection: Record<string, boolean>;
  pagination: PaginationConfig;
  setSorting: (sorting: SortingState[]) => void;
  setColumnVisibility: (visibility: ColumnVisibility) => void;
  setRowSelection: (selection: Record<string, boolean>) => void;
  setPagination: (pagination: Partial<PaginationConfig>) => void;
  getSelectedRows: () => TData[];
  resetSelection: () => void;
  resetFilters: () => void;
}