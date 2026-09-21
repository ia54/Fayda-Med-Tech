import { ColumnDef, CellContext } from "@/components/global/table";
import { Badge } from "@/components/ui/badge";
import {
  DollarSign,
  Users,
  CheckCircle2,
  MoreVertical,
  Edit,
  Trash2,
  Eye,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { cn } from "@/lib/utils";
import type { SubscriptionPlan } from "@/store/api/subscriptionPlansApiSlice";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";

interface GetColumnsParams {
  onView: (subscriptionPlan: SubscriptionPlan) => void;
  onEdit: (subscriptionPlan: SubscriptionPlan) => void;
  onDelete: (id: number) => void;
}

export function getSubscriptionPlansColumns({
  onView,
  onEdit,
  onDelete,
}: GetColumnsParams): ColumnDef<SubscriptionPlan>[] {
  return [
    {
      id: "plan_name",
      header: "Plan Name",
      accessorKey: "plan_name",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
        <div className="flex items-center gap-3">
          <div className="p-2 bg-primary/10 rounded-lg">
            <DollarSign className="h-4 w-4 text-primary" />
          </div>
          <div>
            <div className="font-medium">{row.original.plan_name}</div>
            <div className="text-sm text-muted-foreground">
              ${Number(row.original.price).toFixed(2)}/month
            </div>
          </div>
        </div>
      ),
      enableSorting: true,
    },
    {
      id: "users",
      header: "Users",
      accessorKey: "users",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
        <div className="flex items-center gap-2">
          <Users className="h-4 w-4 text-muted-foreground" />
          <span className="font-medium">{row.original.users}</span>
        </div>
      ),
      enableSorting: true,
    },
    {
      id: "organizations",
      header: "Organizations",
      accessorKey: "organizations",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
        <span className="font-medium">{row.original.organizations}</span>
      ),
      enableSorting: true,
    },
    {
      id: "features",
      header: "Features",
      accessorKey: "features",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
        <div className="space-y-1">
          {row.original.features.slice(0, 2).map((feature, idx) => (
            <div key={idx} className="flex items-center gap-1 text-sm">
              <CheckCircle2 className="h-3 w-3 text-green-600" />
              <span className="text-muted-foreground truncate max-w-50">
                {feature}
              </span>
            </div>
          ))}
          {row.original.features.length > 2 && (
            <div className="text-xs text-muted-foreground">
              +{row.original.features.length - 2} more
            </div>
          )}
        </div>
      ),
      enableSorting: false,
    },
    {
      id: "status",
      header: "Status",
      accessorKey: "status",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
        <Badge
          variant={row.original.status === "active" ? "default" : "secondary"}
          className={cn(
            row.original.status === "active"
              ? "bg-green-100 text-green-800 hover:bg-green-100"
              : "bg-gray-100 text-gray-800 hover:bg-gray-100"
          )}
        >
          {row.original.status === "active" ? "Active" : "Inactive"}
        </Badge>
      ),
      enableSorting: true,
    },
    {
      id: "created_at",
      header: "Created",
      accessorKey: "created_at",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
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
      id: "actions",
      header: "Actions",
      cell: ({ row }: CellContext<SubscriptionPlan>) => (
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
