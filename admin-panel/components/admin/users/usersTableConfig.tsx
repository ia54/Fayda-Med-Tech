import { ColumnDef } from "@/components/global/table";
import { MoreHorizontal, Eye, Pencil, Trash2, Mail, Building2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { Badge } from "@/components/ui/badge";
import { User } from "@/store/api/usersApiSlice";
import { ROLE_LABELS, STATUS_LABELS } from "@/lib/validations/user";
import { format } from "date-fns";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";

interface GetUserColumnsParams {
  onView: (user: User) => void;
  onEdit: (user: User) => void;
  onDelete: (user: User) => void;
}

export function getUserColumns({
  onView,
  onEdit,
  onDelete,
}: GetUserColumnsParams): ColumnDef<User>[] {
  return [
    {
      id: "user",
      accessorKey: "first_name",
      header: "User",
      cell: ({ row }) => {
        const user = row.original;
        const initials = user?.first_name && user?.last_name
          ? `${user.first_name[0]}${user.last_name[0]}`.toUpperCase()
          : "??";

        const fullName = user?.first_name && user?.last_name
          ? `${user.first_name} ${user.last_name}`
          : user?.email?.split('@')[0] || 'Unknown User';

        return (
          <div className="flex items-center gap-3">
            <Avatar className="h-9 w-9">
              <AvatarFallback className="bg-primary/10 text-primary font-medium">
                {initials}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col">
              <span className="font-medium">
                {fullName}
              </span>
              <span className="text-xs text-muted-foreground flex items-center gap-1">
                <Mail className="h-3 w-3" />
                {user?.email || 'No email'}
              </span>
            </div>
          </div>
        );
      },
    },
    {
      id: "organization",
      accessorKey: "organization",
      header: "Organization",
      cell: ({ row }) => {
        const user = row.original;
        const orgName = user?.organization || user?.organization_relation?.org_name || 'N/A';
        return (
          <div className="flex items-center gap-2">
            <Building2 className="h-4 w-4 text-muted-foreground" />
            <span className="text-sm">{orgName}</span>
          </div>
        );
      },
    },
    {
      id: "role",
      accessorKey: "role",
      header: "Role",
      cell: ({ row }) => {
        const role = row.original?.role;

        const roleColors: Record<string, string> = {
          admin: "bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200",
          firm_admin: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
          attorney: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200",
          medical_biller: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200",
          provider_staff: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
          client: "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200",
        };

        if (!role) {
          return <Badge variant="secondary">Unknown</Badge>;
        }

        return (
          <Badge className={roleColors[role] || ""} variant="secondary">
            {ROLE_LABELS[role] || role}
          </Badge>
        );
      },
    },
    {
      id: "status",
      accessorKey: "status",
      header: "Status",
      cell: ({ row }) => {
        const status = row.original?.status;
        return (
          <Badge
            variant={status === "active" ? "default" : "secondary"}
            className={
              status === "active"
                ? "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                : "bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200"
            }
          >
            {status ? STATUS_LABELS[status] || status : 'Unknown'}
          </Badge>
        );
      },
    },
    {
      id: "last_login",
      accessorKey: "last_login",
      header: "Last Login",
      cell: ({ row }) => {
        const lastLogin = row.original?.last_login;

        if (!lastLogin) {
          return <span className="text-sm text-muted-foreground">Never</span>;
        }

        try {
          return (
            <div className="flex flex-col">
              <span className="text-sm">{format(new Date(lastLogin), "MMM dd, yyyy")}</span>
              <span className="text-xs text-muted-foreground">
                {format(new Date(lastLogin), "h:mm a")}
              </span>
            </div>
          );
        } catch (error) {
          return <span className="text-sm text-muted-foreground">Invalid date</span>;
        }
      },
    },
    {
      id: "email_verified",
      accessorKey: "email_verified_at",
      header: "Email Verified",
      cell: ({ row }) => {
        const verified = row.original?.email_verified_at;
        return (
          <Badge
            variant={verified ? "default" : "outline"}
            className={
              verified
                ? "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                : ""
            }
          >
            {verified ? "Verified" : "Pending"}
          </Badge>
        );
      },
    },
    {
      id: "created_at",
      accessorKey: "created_at",
      header: "Created",
      cell: ({ row }) => {
        const date = row.original?.created_at;
        if (!date) {
          return <span className="text-sm text-muted-foreground">Unknown</span>;
        }
        try {
          return (
            <span className="text-sm">
              {format(new Date(date), "MMM dd, yyyy")}
            </span>
          );
        } catch (error) {
          return <span className="text-sm text-muted-foreground">Invalid date</span>;
        }
      },
    },
    {
      id: "actions",
      header: "Actions",
      enableHiding: false,
      enableSorting: false,
      cell: ({ row }) => {
        const user = row.original;

        return (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="h-8 w-8 p-0 cursor-pointer">
                <span className="sr-only">Open menu</span>
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onClick={() => onView(user)} className="cursor-pointer">
                <Eye className="mr-2 h-4 w-4" />
                View Details
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => onEdit(user)} className="cursor-pointer">
                <Pencil className="mr-2 h-4 w-4" />
                Edit
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => onDelete(user)}
                className="text-destructive focus:text-destructive cursor-pointer"
              >
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    },
  ];
}
