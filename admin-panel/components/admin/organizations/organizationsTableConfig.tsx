"use client";

import { ColumnDef } from "@/components/global/table";
import { Organization } from "@/store/api/organizationsApiSlice";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { Edit, Eye, MoreHorizontal, Trash2 } from "lucide-react";
import { formatCurrency } from "@/lib/utils";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";

interface OrganizationActionsProps {
  organization: Organization;
  onView: (org: Organization) => void;
  onEdit: (org: Organization) => void;
  onDelete: (org: Organization) => void;
}

function OrganizationActions({
  organization,
  onView,
  onEdit,
  onDelete,
}: OrganizationActionsProps) {
  const dispatch = useAppDispatch();

  const handleDelete = () => {
    dispatch(
      openModal({
        type: "confirm",
        props: {
          title: "Delete Organization",
          message: `Are you sure you want to delete "${organization.org_name}"? This action cannot be undone.`,
          onConfirm: () => onDelete(organization),
        },
      })
    );
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-8 w-8 p-0 cursor-pointer">
          <span className="sr-only">Open menu</span>
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem
          onClick={() => onView(organization)}
          className="cursor-pointer"
        >
          <Eye className="mr-2 h-4 w-4" />
          View Details
        </DropdownMenuItem>
        <DropdownMenuItem
          onClick={() => onEdit(organization)}
          className="cursor-pointer"
        >
          <Edit className="mr-2 h-4 w-4" />
          Edit
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={handleDelete}
          className="text-destructive focus:text-destructive cursor-pointer"
        >
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export function getOrganizationColumns(actions: {
  onView: (org: Organization) => void;
  onEdit: (org: Organization) => void;
  onDelete: (org: Organization) => void;
}): ColumnDef<Organization>[] {
  return [
    {
      id: "org_name",
      accessorKey: "org_name",
      header: "Organization Name",
      enableSorting: true,
      meta: {
        minWidth: "250px",
      },
      cell: ({ row, value }) => (
        <div className="flex items-center gap-2">
          {row.original?.company_logo_url && (
            <img
              src={row.original?.company_logo_url}
              alt={value}
              className="h-8 w-8 rounded-full object-cover flex-shrink-0"
            />
          )}
          <span className="font-medium truncate">{value}</span>
        </div>
      ),
    },
    {
      id: "org_type",
      accessorKey: "org_type",
      header: "Type",
      enableSorting: true,
      meta: {
        minWidth: "140px",
      },
      cell: ({ value }) => (
        <Badge variant="outline" className="capitalize">
          {value}
        </Badge>
      ),
    },
    {
      id: "subscription_plan",
      accessorKey: "subscription_plan",
      header: "Plan",
      enableSorting: true,
      meta: {
        minWidth: "130px",
      },
      cell: ({ value }) => {
        const variant =
          value === "Enterprise"
            ? "default"
            : value === "Premium"
            ? "secondary"
            : "outline";

        return <Badge variant={variant}>{value}</Badge>;
      },
    },
    {
      id: "email",
      accessorKey: "email",
      header: "Email",
      enableSorting: true,
      meta: {
        minWidth: "200px",
      },
      cell: ({ value }) => (
        <a
          href={`mailto:${value}`}
          className="text-sm text-muted-foreground hover:text-foreground hover:underline cursor-pointer truncate block"
        >
          {value}
        </a>
      ),
    },
    {
      id: "no_of_employees",
      accessorKey: "no_of_employees",
      header: "Employees",
      enableSorting: true,
      meta: {
        minWidth: "120px",
      },
      cell: ({ value }) => <span className="text-sm">{value || "N/A"}</span>,
    },
    {
      id: "monthly_revenue",
      accessorKey: "monthly_revenue",
      header: "Monthly Revenue",
      enableSorting: true,
      meta: {
        minWidth: "150px",
      },
      cell: ({ value }) => (
        <span className="text-sm font-medium">
          {value ? formatCurrency(value) : "N/A"}
        </span>
      ),
    },
    {
      id: "yearly_revenue",
      accessorKey: "yearly_revenue",
      header: "Yearly Revenue",
      enableSorting: true,
      meta: {
        minWidth: "150px",
      },
      cell: ({ value }) => (
        <span className="text-sm font-medium">
          {value ? formatCurrency(value) : "N/A"}
        </span>
      ),
    },
    {
      id: "created_at",
      accessorKey: "created_at",
      header: "Created",
      enableSorting: true,
      meta: {
        minWidth: "130px",
      },
      cell: ({ value }) => (
        <span className="text-sm text-muted-foreground">
          {new Date(value).toLocaleDateString()}
        </span>
      ),
    },
    {
      id: "actions",
      header: "Actions",
      enableHiding: false,
      enableSorting: false,
      cell: ({ row }) => (
        <OrganizationActions
          organization={row?.original}
          onView={actions.onView}
          onEdit={actions.onEdit}
          onDelete={actions.onDelete}
        />
      ),
    },
  ];
}
