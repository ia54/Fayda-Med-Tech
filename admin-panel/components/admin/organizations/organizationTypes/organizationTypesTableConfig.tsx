import { ColumnDef } from "@/components/global/table";
import { Badge } from "@/components/ui/badge";
import { Building2, MoreVertical, Edit, Trash2, Eye } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import type { OrganizationType } from "@/store/api/organizationTypesApiSlice";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";

interface GetColumnsParams {
  onView: (organizationType: OrganizationType) => void;
  onEdit: (organizationType: OrganizationType) => void;
  onDelete: (id: number) => void;
}

export function getOrganizationTypesColumns({
  onView,
  onEdit,
  onDelete,
}: GetColumnsParams): ColumnDef<OrganizationType>[] {

  return [
    {
      id: "type_name",
      header: "Type Name",
      accessorKey: "type_name",
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <div className="p-2 bg-primary/10 rounded-lg">
            <Building2 className="h-4 w-4 text-primary" />
          </div>
          <div>
            <div className="font-medium">{row.original.type_name}</div>
            <div className="text-sm text-muted-foreground line-clamp-1">
              {row.original.description}
            </div>
          </div>
        </div>
      ),
      enableSorting: true,
    },
    {
      id: "created_at",
      header: "Created",
      accessorKey: "created_at",
      cell: ({ row }) => (
        <span className="text-sm text-muted-foreground">
          {new Date(row.original.created_at).toLocaleDateString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
          })}
        </span>
      ),
      enableSorting: true,
    },
    {
      id: "updated_at",
      header: "Last Updated",
      accessorKey: "updated_at",
      cell: ({ row }) => (
        <span className="text-sm text-muted-foreground">
          {new Date(row.original.updated_at).toLocaleDateString("en-US", {
            year: "numeric",
            month: "short",
            day: "numeric",
          })}
        </span>
      ),
      enableSorting: true,
    },
    {
      id: "actions",
      header: "Actions",
      cell: ({ row }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
              <MoreVertical className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem
              onClick={() => onView(row.original)}
              className="cursor-pointer"
            >
              <Eye className="mr-2 h-4 w-4" />
              View
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => onEdit(row.original)}
              className="cursor-pointer"
            >
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem
              onClick={() => onDelete(row.original.id)}
              className="text-destructive cursor-pointer"
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
      enableSorting: false,
    },
  ];
}
