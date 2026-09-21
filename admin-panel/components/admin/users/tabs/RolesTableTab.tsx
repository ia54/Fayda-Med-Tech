"use client";

import { useMemo } from "react";
import { DataTable } from "@/components/global/table";
import { ColumnDef } from "@/components/global/table/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  MoreHorizontal,
  Eye,
  Pencil,
  Trash2,
  Shield,
  Users,
  Lock,
} from "lucide-react";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

interface Role {
  id: number;
  name: string;
  display_name: string;
  description: string;
  permissions_count: number;
  users_count: number;
  is_system_role: boolean;
  created_at: string;
  updated_at: string;
}

// Dummy data — matches PDF Section 2 official roles
const DUMMY_ROLES: Role[] = [
  {
    id: 1,
    name: "admin",
    display_name: "Super Admin",
    description: "FaydaTech platform team — full system access",
    permissions_count: 50,
    users_count: 3,
    is_system_role: true,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-01-15T00:00:00Z",
  },
  {
    id: 2,
    name: "firm_admin",
    display_name: "Firm Admin",
    description: "Law firm owner/office manager — full tenant access",
    permissions_count: 42,
    users_count: 8,
    is_system_role: true,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-02-10T00:00:00Z",
  },
  {
    id: 3,
    name: "attorney",
    display_name: "Attorney",
    description: "Lawyer/paralegal — case-focused access",
    permissions_count: 30,
    users_count: 15,
    is_system_role: true,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-02-20T00:00:00Z",
  },
  {
    id: 4,
    name: "medical_biller",
    display_name: "Medical Biller",
    description: "Billing specialist — billing-focused access",
    permissions_count: 25,
    users_count: 10,
    is_system_role: true,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-03-01T00:00:00Z",
  },
  {
    id: 5,
    name: "provider_staff",
    display_name: "Provider Staff",
    description: "Doctor's office staff — provider portal access",
    permissions_count: 20,
    users_count: 45,
    is_system_role: true,
    created_at: "2024-01-01T00:00:00Z",
    updated_at: "2024-03-15T00:00:00Z",
  },
  {
    id: 6,
    name: "client",
    display_name: "Client (Patient)",
    description: "Injured patient/claimant — self-service portal access",
    permissions_count: 12,
    users_count: 120,
    is_system_role: true,
    created_at: "2024-02-01T00:00:00Z",
    updated_at: "2024-03-20T00:00:00Z",
  },
];


export function RolesTableTab() {
  const columns = useMemo<ColumnDef<Role>[]>(
    () => [
      {
        id: "role",
        accessorKey: "display_name",
        header: "Role",
        cell: ({ row }) => {
          const role = row.original;
          const initial = role.display_name[0].toUpperCase();

          const roleColors: Record<string, string> = {
            admin: "bg-purple-500",
            firm_admin: "bg-emerald-500",
            attorney: "bg-blue-500",
            medical_biller: "bg-yellow-500",
            provider_staff: "bg-green-500",
            client: "bg-orange-500",
          };

          return (
            <div className="flex items-center gap-3">
              <Avatar className="h-9 w-9">
                <AvatarFallback
                  className={`${
                    roleColors[role.name] || "bg-primary"
                  } text-white font-medium`}
                >
                  {initial}
                </AvatarFallback>
              </Avatar>
              <div className="flex flex-col">
                <span className="font-medium">{role.display_name}</span>
                <span className="text-xs text-muted-foreground flex items-center gap-1">
                  <Lock className="h-3 w-3" />
                  {role.name}
                </span>
              </div>
            </div>
          );
        },
      },
      {
        id: "description",
        accessorKey: "description",
        header: "Description",
        cell: ({ row }) => {
          return (
            <span className="text-sm text-muted-foreground">
              {row.original.description}
            </span>
          );
        },
      },
      {
        id: "permissions",
        accessorKey: "permissions_count",
        header: "Permissions",
        cell: ({ row }) => {
          return (
            <div className="flex items-center gap-2">
              <Shield className="h-4 w-4 text-muted-foreground" />
              <span className="text-sm font-medium">
                {row.original.permissions_count}
              </span>
            </div>
          );
        },
      },
      {
        id: "users",
        accessorKey: "users_count",
        header: "Users",
        cell: ({ row }) => {
          return (
            <div className="flex items-center gap-2">
              <Users className="h-4 w-4 text-muted-foreground" />
              <span className="text-sm font-medium">
                {row.original.users_count}
              </span>
            </div>
          );
        },
      },
      {
        id: "type",
        accessorKey: "is_system_role",
        header: "Type",
        cell: ({ row }) => {
          const isSystem = row.original.is_system_role;
          return (
            <Badge
              variant={isSystem ? "default" : "secondary"}
              className={
                isSystem
                  ? "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  : "bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200"
              }
            >
              {isSystem ? "System" : "Custom"}
            </Badge>
          );
        },
      },
      {
        id: "actions",
        header: "Actions",
        cell: ({ row }) => {
          const role = row.original;

          return (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-8 w-8 p-0">
                  <span className="sr-only">Open menu</span>
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem>
                  <Eye className="mr-2 h-4 w-4" />
                  View Details
                </DropdownMenuItem>
                <DropdownMenuItem disabled={role.is_system_role}>
                  <Pencil className="mr-2 h-4 w-4" />
                  Edit Role
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  disabled={role.is_system_role}
                  className="text-red-600 focus:text-red-600"
                >
                  <Trash2 className="mr-2 h-4 w-4" />
                  Delete Role
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          );
        },
      },
    ],
    []
  );

  return (
    <div>
      {/* Info Banner */}
      <div className="mb-4 p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div className="flex items-start gap-3">
          <Shield className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" />
          <div>
            <h4 className="text-sm font-medium text-blue-900 dark:text-blue-100">
              Roles & Permissions
            </h4>
            <p className="text-sm text-blue-700 dark:text-blue-300 mt-1">
              Manage user roles and their associated permissions. System roles cannot
              be modified or deleted. This is currently showing dummy data for
              demonstration purposes.
            </p>
          </div>
        </div>
      </div>

      {/* Data Table */}
      <DataTable
        columns={columns}
        data={DUMMY_ROLES}
        loading={{ isLoading: false }}
        pagination={{
          page: 1,
          pageSize: 10,
          total: DUMMY_ROLES.length,
        }}
      />
    </div>
  );
}
