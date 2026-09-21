import { Users, UserCheck, UserX, Shield, Briefcase, Plus } from "lucide-react";

export const getStatsCards = (stats: {
  totalUsers: number;
  activeUsers: number;
  inactiveUsers: number;
  adminUsers: number;
  providerStaff: number;
}) => [
  {
    id: "total",
    title: "Total Users",
    value: stats.totalUsers.toLocaleString(),
    icon: <Users className="h-4 w-4" />,
    description: "All registered users",
  },
  {
    id: "active",
    title: "Active Users",
    value: stats.activeUsers.toLocaleString(),
    icon: <UserCheck className="h-4 w-4" />,
    description: "Currently active accounts",
    trend: {
      value: stats.totalUsers > 0 ? ((stats.activeUsers / stats.totalUsers) * 100) : 0,
      isPositive: true,
    },
  },
  {
    id: "inactive",
    title: "Inactive Users",
    value: stats.inactiveUsers.toLocaleString(),
    icon: <UserX className="h-4 w-4" />,
    description: "Disabled accounts",
  },
  {
    id: "admin",
    title: "Administrators",
    value: stats.adminUsers.toLocaleString(),
    icon: <Shield className="h-4 w-4" />,
    description: "Admin role users",
  },
  {
    id: "provider",
    title: "Provider Staff",
    value: stats.providerStaff.toLocaleString(),
    icon: <Briefcase className="h-4 w-4" />,
    description: "Healthcare providers",
  },
];

export const getPageHeader = (
  onAddClick: () => void,
  onExport: (format: "csv" | "excel" | "json" | "pdf") => void
) => ({
  title: "User Management",
  description: "Manage system users, roles, and permissions",
  actions: [
    {
      label: "Add User",
      icon: <Plus className="h-4 w-4" />,
      onClick: onAddClick,
    },
  ],
  exportConfig: {
    onExport,
    formats: ["csv", "excel", "json"] as ("csv" | "excel" | "json" | "pdf")[],
  },
});

export function getEmptyState() {
  return {
    icon: Users,
    title: "No users found",
    description: "Get started by creating your first user.",
    action: {
      label: "Add User",
    },
  };
}

export function getLoadingState() {
  return {
    message: "Loading users...",
  };
}
