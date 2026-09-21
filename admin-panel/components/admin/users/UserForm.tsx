"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2, Mail, Lock, User, Building2, Shield, CheckCircle2 } from "lucide-react";
import { LoadingButton } from "@/components/ui/loading-button";
import { User as UserType } from "@/store/api/usersApiSlice";
import { useGetOrganizationsQuery } from "@/store/api/organizationsApiSlice";
import {
  type UserFormValues,
  ROLE_LABELS,
  STATUS_LABELS,
  UserRoleEnum,
  UserStatusEnum,
} from "@/lib/validations/user";
import { Checkbox } from "@/components/ui/checkbox";

import { useAuth } from "@/hooks/useAuth";

interface UserFormProps {
  user?: UserType | null;
  onSubmit: (data: UserFormValues & { send_email?: boolean }) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
}

export function UserForm({
  user,
  onSubmit,
  onCancel,
  isLoading = false,
  apiErrors,
}: UserFormProps) {
  const { user: authUser } = useAuth();
  const isAdmin = authUser?.role === "admin";

  // Fetch organizations for dropdown (only if super admin)
  const { data: orgsResponse, isLoading: isLoadingOrgs } = useGetOrganizationsQuery(
    {
      page: 1,
      per_page: 100, // Get all organizations
    },
    { skip: !isAdmin }
  );

  const organizations = orgsResponse?.data?.organizations || [];

  const [formData, setFormData] = useState({
    first_name: user?.first_name || "",
    last_name: user?.last_name || "",
    email: user?.email || "",
    password: "",
    organization_id: user?.organization_id || authUser?.organization_id || 0,
    role: user?.role || ("attorney" as const),
    status: user?.status || ("active" as const),
  });

  const [sendEmail, setSendEmail] = useState(true);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Merge API errors with local errors
  const displayErrors = {
    ...errors,
    ...(apiErrors
      ? Object.entries(apiErrors).reduce((acc, [key, messages]) => {
          acc[key] = messages[0]; // Display first error message
          return acc;
        }, {} as Record<string, string>)
      : {}),
  };

  const handleChange = (field: string, value: any) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (displayErrors[field]) {
      setErrors((prev) => ({ ...prev, [field]: "" }));
    }
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.first_name.trim()) {
      newErrors.first_name = "First name is required";
    }

    if (!formData.last_name.trim()) {
      newErrors.last_name = "Last name is required";
    }

    if (!formData.email.trim()) {
      newErrors.email = "Email is required";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = "Invalid email address";
    }

    // Password validation (required for create, optional for edit)
    if (!user && !formData.password) {
      newErrors.password = "Password is required";
    } else if (formData.password && formData.password.length < 8) {
      newErrors.password = "Password must be at least 8 characters";
    } else if (
      formData.password &&
      !/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(formData.password)
    ) {
      newErrors.password =
        "Password must contain uppercase, lowercase, and number";
    }

    if (!formData.organization_id || formData.organization_id === 0) {
      newErrors.organization_id = "Organization is required";
    }

    if (!formData.role) {
      newErrors.role = "Role is required";
    }

    if (!formData.status) {
      newErrors.status = "Status is required";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validate()) return;

    try {
      const submitData: any = {
        ...formData,
        organization_id: Number(formData.organization_id),
      };

      // Only include password if provided
      if (!formData.password) {
        delete submitData.password;
      }

      // Add send_email only for create mode
      if (!user) {
        submitData.send_email = sendEmail;
      }

      await onSubmit(submitData);
    } catch (error) {
      // Error is handled by parent
      console.error("Form submission error:", error);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {/* Name Fields Row */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* First Name */}
        <div className="space-y-2">
          <Label htmlFor="first_name">
            First Name <span className="text-red-500">*</span>
          </Label>
          <div className="relative">
            <User className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <Input
              id="first_name"
              type="text"
              placeholder="John"
              value={formData.first_name}
              onChange={(e) => handleChange("first_name", e.target.value)}
              className={`pl-9 ${displayErrors.first_name ? "border-red-500" : ""}`}
              disabled={isLoading}
            />
          </div>
          {displayErrors.first_name && (
            <p className="text-sm text-red-500">{displayErrors.first_name}</p>
          )}
        </div>

        {/* Last Name */}
        <div className="space-y-2">
          <Label htmlFor="last_name">
            Last Name <span className="text-red-500">*</span>
          </Label>
          <div className="relative">
            <User className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
            <Input
              id="last_name"
              type="text"
              placeholder="Doe"
              value={formData.last_name}
              onChange={(e) => handleChange("last_name", e.target.value)}
              className={`pl-9 ${displayErrors.last_name ? "border-red-500" : ""}`}
              disabled={isLoading}
            />
          </div>
          {displayErrors.last_name && (
            <p className="text-sm text-red-500">{displayErrors.last_name}</p>
          )}
        </div>
      </div>

      {/* Email */}
      <div className="space-y-2">
        <Label htmlFor="email">
          Email <span className="text-red-500">*</span>
        </Label>
        <div className="relative">
          <Mail className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
          <Input
            id="email"
            type="email"
            placeholder="john.doe@example.com"
            value={formData.email}
            onChange={(e) => handleChange("email", e.target.value)}
            className={`pl-9 ${displayErrors.email ? "border-red-500" : ""}`}
            disabled={isLoading}
          />
        </div>
        {displayErrors.email && (
          <p className="text-sm text-red-500">{displayErrors.email}</p>
        )}
      </div>

      {/* Password */}
      <div className="space-y-2">
        <Label htmlFor="password">
          Password {!user && <span className="text-red-500">*</span>}
          {user && (
            <span className="text-xs text-muted-foreground ml-2">
              (Leave blank to keep current password)
            </span>
          )}
        </Label>
        <div className="relative">
          <Lock className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
          <Input
            id="password"
            type="password"
            placeholder={user ? "••••••••" : "Enter a strong password"}
            value={formData.password}
            onChange={(e) => handleChange("password", e.target.value)}
            className={`pl-9 ${displayErrors.password ? "border-red-500" : ""}`}
            disabled={isLoading}
          />
        </div>
        {displayErrors.password && (
          <p className="text-sm text-red-500">{displayErrors.password}</p>
        )}
        {!user && (
          <p className="text-xs text-muted-foreground">
            Must be at least 8 characters with uppercase, lowercase, and number
          </p>
        )}
      </div>

      {/* Organization - Only for Super Admin */}
      {isAdmin && (
        <div className="space-y-2">
          <Label htmlFor="organization_id">
            Organization <span className="text-red-500">*</span>
          </Label>
          <div className="relative">
            <Building2 className="absolute left-3 top-3 h-4 w-4 text-muted-foreground z-10" />
            <Select
              value={formData.organization_id.toString()}
              onValueChange={(value) =>
                handleChange("organization_id", Number(value))
              }
              disabled={isLoading || isLoadingOrgs}
            >
              <SelectTrigger
                className={`pl-9 ${
                  displayErrors.organization_id ? "border-red-500" : ""
                }`}
              >
                <SelectValue placeholder="Select an organization" />
              </SelectTrigger>
              <SelectContent>
                {isLoadingOrgs ? (
                  <div className="flex items-center justify-center py-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span className="ml-2">Loading organizations...</span>
                  </div>
                ) : organizations.length === 0 ? (
                  <div className="py-2 px-2 text-sm text-muted-foreground">
                    No organizations found
                  </div>
                ) : (
                  organizations.map((org) => (
                    <SelectItem key={org.id} value={org.id.toString()}>
                      {org.org_name}
                    </SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
          </div>
          {displayErrors.organization_id && (
            <p className="text-sm text-red-500">
              {displayErrors.organization_id}
            </p>
          )}
        </div>
      )}

      {/* Role and Status Row */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Role */}
        <div className="space-y-2">
          <Label htmlFor="role">
            Role <span className="text-red-500">*</span>
          </Label>
          <div className="relative">
            <Shield className="absolute left-3 top-3 h-4 w-4 text-muted-foreground z-10" />
            <Select
              value={formData.role}
              onValueChange={(value) => handleChange("role", value)}
              disabled={isLoading}
            >
              <SelectTrigger
                className={`pl-9 ${displayErrors.role ? "border-red-500" : ""}`}
              >
                <SelectValue placeholder="Select a role" />
              </SelectTrigger>
              <SelectContent>
                {UserRoleEnum.options
                  .filter((role) => isAdmin || role !== "admin")
                  .map((role) => (
                    <SelectItem key={role} value={role}>
                      {ROLE_LABELS[role]}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
          {displayErrors.role && (
            <p className="text-sm text-red-500">{displayErrors.role}</p>
          )}
        </div>

        {/* Status */}
        <div className="space-y-2">
          <Label htmlFor="status">
            Status <span className="text-red-500">*</span>
          </Label>
          <div className="relative">
            <CheckCircle2 className="absolute left-3 top-3 h-4 w-4 text-muted-foreground z-10" />
            <Select
              value={formData.status}
              onValueChange={(value) => handleChange("status", value)}
              disabled={isLoading}
            >
              <SelectTrigger
                className={`pl-9 ${displayErrors.status ? "border-red-500" : ""}`}
              >
                <SelectValue placeholder="Select status" />
              </SelectTrigger>
              <SelectContent>
                {UserStatusEnum.options.map((status) => (
                  <SelectItem key={status} value={status}>
                    {STATUS_LABELS[status]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {displayErrors.status && (
            <p className="text-sm text-red-500">{displayErrors.status}</p>
          )}
        </div>
      </div>

      {/* Send Welcome Email (only for create mode) */}
      {!user && (
        <div className="flex items-center space-x-2 p-4 bg-muted rounded-lg">
          <Checkbox
            id="send_email"
            checked={sendEmail}
            onCheckedChange={(checked) => setSendEmail(checked as boolean)}
            disabled={isLoading}
          />
          <Label
            htmlFor="send_email"
            className="text-sm font-normal cursor-pointer"
          >
            Send welcome email with login credentials to the user
          </Label>
        </div>
      )}

      {/* Form Actions */}
      <div className="flex justify-end gap-3 pt-4 border-t">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isLoading}
        >
          Cancel
        </Button>
        <LoadingButton type="submit" loading={isLoading}>
          {user ? "Update User" : "Create User"}
        </LoadingButton>
      </div>
    </form>
  );
}
