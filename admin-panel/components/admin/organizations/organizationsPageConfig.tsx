import { Building2, Users, DollarSign, TrendingUp, Plus } from "lucide-react";

export const getStatsCards = (stats: {
  total: number;
  premium: number;
  enterprise: number;
  basic: number;
}) => [
  {
    id: "total",
    title: "Total Organizations",
    value: stats.total.toLocaleString(),
    icon: <Building2 className="h-4 w-4" />,
    description: "All registered",
  },
  {
    id: "premium",
    title: "Premium Plans",
    value: stats.premium.toLocaleString(),
    icon: <TrendingUp className="h-4 w-4" />,
    description: "Premium subscriptions",
  },
  {
    id: "enterprise",
    title: "Enterprise Plans",
    value: stats.enterprise.toLocaleString(),
    icon: <DollarSign className="h-4 w-4" />,
    description: "Enterprise clients",
  },
  {
    id: "basic",
    title: "Basic/Free Plans",
    value: stats.basic.toLocaleString(),
    icon: <Users className="h-4 w-4" />,
    description: "Basic tier",
  },
];

export const getPageHeader = (onAddClick: () => void, onExport: (format: "csv" | "excel" | "json" | "pdf") => void) => ({
  title: "Organizations",
  description: "Manage all registered organizations and their settings",
  actions: [
    {
      label: "Add Organization",
      icon: <Plus className="h-4 w-4" />,
      onClick: onAddClick,
    },
  ],
  exportConfig: {
    onExport,
    formats: ["csv", "excel", "json"] as ("csv" | "excel" | "json" | "pdf")[],
  },
});

export const getFilterBar = (
  searchTerm: string,
  onSearchChange: (value: string) => void,
  dateRange: any,
  onDateRangeChange: (range: any) => void,
  onRefresh: () => void
) => ({
  searchValue: searchTerm,
  onSearchChange,
  searchPlaceholder: "Type to search...",
  showDateRange: true,
  dateRangeValue: dateRange,
  onDateRangeChange,
  dateRangePlaceholder: "Filter by date range...",
  showRefresh: true,
  onRefresh,
  showColumnToggle: true,
});

export const getEmptyState = (onAddClick: () => void) => ({
  title: "No organizations found",
  description: "Get started by adding your first organization to the system.",
  action: {
    label: "Add First Organization",
    onClick: onAddClick,
  },
});

export const getLoadingState = (isLoading: boolean) => ({
  isLoading,
  rowCount: 10,
  message: isLoading ? "Loading organizations..." : undefined,
});
