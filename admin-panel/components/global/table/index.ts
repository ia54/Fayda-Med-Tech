// Main DataTable Component
export { DataTable } from "./DataTable";

// Layout Components (use these separately from DataTable)
export { PageHeader } from "./PageHeader";
export { FilterBar } from "./FilterBar";
export { StatsCards } from "./StatsCards";

// Sub-components (can be used independently)
export { TableActions, BulkActions } from "./TableActions";
export { TableToolbar } from "./TableToolbar";
export { FilterSection, InlineFilterSection } from "./FilterSection";
export { CardStats } from "./CardStats";
export { TableContent } from "./TableContent";
export { Pagination, SimplePagination } from "./Pagination";
export {
  TableErrorBoundary,
  TableLoading,
  EmptyState,
  ErrorState,
  NoResults,
} from "./TableStates";

// Hooks
export { useTableState, useColumnVisibility, useSorting } from "./useTableState";

// Types
export type {
  ColumnDef,
  CellContext,
  SortingState,
  PaginationConfig,
  PaginationHandlers,
  ColumnVisibility,
  TableAction,
  ExportConfig,
  ExportFormat,
  ToolbarConfig,
  FilterSectionProps,
  CardStatsConfig,
  StatCard,
  LoadingConfig,
  EmptyStateConfig,
  ErrorStateConfig,
  DataTableProps,
  TableState,
  DateRange,
  PageHeaderConfig,
  FilterBarConfig,
} from "./types";
