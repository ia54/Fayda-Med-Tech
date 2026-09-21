"use client";

import { ReusableModal } from "@/components/ui/reusable-modal";
import { UserForm } from "./UserForm";
import { User, UserFormData } from "@/store/api/usersApiSlice";
import { Badge } from "@/components/ui/badge";
import { ROLE_LABELS, STATUS_LABELS } from "@/lib/validations/user";
import { format } from "date-fns";
import { Mail, Building2, Shield, Calendar, Clock, CheckCircle2 } from "lucide-react";

interface UserModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: "create" | "edit" | "view";
  user?: User | null;
  onSubmit: (data: UserFormData) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
}

export function UserModal({
  open,
  onOpenChange,
  mode,
  user,
  onSubmit,
  isLoading = false,
  apiErrors,
}: UserModalProps) {
  const getTitle = () => {
    switch (mode) {
      case "create":
        return "Create New User";
      case "edit":
        return "Edit User";
      case "view":
        return "View User Details";
      default:
        return "";
    }
  };

  const getDescription = () => {
    switch (mode) {
      case "create":
        return "Add a new user to the system. An email with login credentials will be sent if enabled.";
      case "edit":
        return "Update user information and permissions.";
      case "view":
        return "View detailed information about this user.";
      default:
        return "";
    }
  };

  // View mode body
  const viewBody = user ? (
    <div className="space-y-6">
      {/* Header with Name and Status */}
      <div className="flex items-center justify-between pb-4 border-b">
        <div>
          <h3 className="text-xl font-semibold">
            {user.first_name} {user.last_name}
          </h3>
          <p className="text-sm text-muted-foreground">{user.email}</p>
        </div>
        <Badge
          variant={user.status === "active" ? "default" : "secondary"}
          className="text-xs"
        >
          {STATUS_LABELS[user.status]}
        </Badge>
      </div>

      {/* User Details Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Email */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Mail className="h-4 w-4" />
            Email Address
          </div>
          <p className="text-sm">{user.email}</p>
        </div>

        {/* Organization */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Building2 className="h-4 w-4" />
            Organization
          </div>
          <p className="text-sm">{user.organization || user.organization_relation?.org_name || 'N/A'}</p>
        </div>

        {/* Role */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Shield className="h-4 w-4" />
            Role
          </div>
          <Badge variant="outline">{ROLE_LABELS[user.role]}</Badge>
        </div>

        {/* Status */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <CheckCircle2 className="h-4 w-4" />
            Account Status
          </div>
          <Badge
            variant={user.status === "active" ? "default" : "secondary"}
          >
            {STATUS_LABELS[user.status]}
          </Badge>
        </div>

        {/* Last Login */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Clock className="h-4 w-4" />
            Last Login
          </div>
          <p className="text-sm">
            {user.last_login
              ? format(new Date(user.last_login), "PPp")
              : "Never"}
          </p>
        </div>

        {/* Email Verified */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <CheckCircle2 className="h-4 w-4" />
            Email Verified
          </div>
          <p className="text-sm">
            {user.email_verified_at
              ? format(new Date(user.email_verified_at), "PPp")
              : "Not verified"}
          </p>
        </div>

        {/* Created At */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Calendar className="h-4 w-4" />
            Created At
          </div>
          <p className="text-sm">
            {format(new Date(user.created_at), "PPp")}
          </p>
        </div>

        {/* Updated At */}
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
            <Calendar className="h-4 w-4" />
            Updated At
          </div>
          <p className="text-sm">
            {format(new Date(user.updated_at), "PPp")}
          </p>
        </div>
      </div>
    </div>
  ) : null;

  // Form mode body
  const formBody = (
    <>
      {/* Error Banner */}
      {apiErrors && Object.keys(apiErrors).length > 0 && (
        <div className="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <p className="text-sm font-medium text-red-800 dark:text-red-200">
            Please fix the following errors:
          </p>
          <ul className="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
            {Object.entries(apiErrors).map(([field, messages]) => (
              <li key={field}>
                {field}: {messages[0]}
              </li>
            ))}
          </ul>
        </div>
      )}

      <UserForm
        user={user}
        onSubmit={onSubmit}
        onCancel={() => onOpenChange(false)}
        isLoading={isLoading}
        apiErrors={apiErrors}
      />
    </>
  );

  return (
    <ReusableModal
      open={open}
      onOpenChange={onOpenChange}
      title={getTitle()}
      body={mode === "view" ? viewBody : formBody}
    />
  );
}
