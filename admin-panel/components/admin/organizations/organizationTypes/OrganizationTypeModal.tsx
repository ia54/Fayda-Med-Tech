"use client";

import { useState, useEffect } from "react";
import { ReusableModal } from "@/components/ui/reusable-modal";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { AlertCircle, Loader2 } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import type { OrganizationType } from "@/store/api/organizationTypesApiSlice";
import type { OrganizationTypeModalMode } from "./useOrganizationTypeModal";

interface OrganizationTypeModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: OrganizationTypeModalMode;
  organizationType: OrganizationType | null;
  onSubmit: (data: { type_name: string; description: string }) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
  errorMessage?: string;
}

export function OrganizationTypeModal({
  open,
  onOpenChange,
  mode,
  organizationType,
  onSubmit,
  isLoading = false,
  apiErrors = {},
  errorMessage = "",
}: OrganizationTypeModalProps) {
  const [formData, setFormData] = useState({
    type_name: "",
    description: "",
  });

  // Reset form when modal opens with data
  useEffect(() => {
    if (open && organizationType) {
      setFormData({
        type_name: organizationType.type_name,
        description: organizationType.description,
      });
    } else if (open && mode === "create") {
      setFormData({
        type_name: "",
        description: "",
      });
    }
  }, [open, organizationType, mode]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onSubmit(formData);
  };

  const isViewMode = mode === "view";
  const isEditMode = mode === "edit";
  const isCreateMode = mode === "create";

  const title =
    mode === "create"
      ? "Add Organization Type"
      : mode === "edit"
      ? "Edit Organization Type"
      : "View Organization Type";

  // Modal Body
  const body = (
    <form onSubmit={handleSubmit} id="organization-type-form">
      {/* Error Message */}
      {errorMessage && (
        <Alert variant="destructive" className="mb-4">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{errorMessage}</AlertDescription>
        </Alert>
      )}

      <div className="space-y-4">
        {/* Type Name */}
        <div className="space-y-2">
          <Label htmlFor="type_name">
            Type Name <span className="text-destructive">*</span>
          </Label>
          <Input
            id="type_name"
            placeholder="e.g., Hospital, Clinic, Pharmacy"
            value={formData.type_name}
            onChange={(e) =>
              setFormData({ ...formData, type_name: e.target.value })
            }
            disabled={isViewMode || isLoading}
            className={
              apiErrors.type_name ? "border-destructive" : "border-accent"
            }
          />
          {apiErrors.type_name && (
            <p className="text-sm text-destructive">{apiErrors.type_name[0]}</p>
          )}
        </div>

        {/* Description */}
        <div className="space-y-2">
          <Label htmlFor="description">
            Description <span className="text-destructive">*</span>
          </Label>
          <Textarea
            id="description"
            placeholder="Describe this organization type..."
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })
            }
            disabled={isViewMode || isLoading}
            rows={4}
            className={
              apiErrors.description
                ? "border-destructive"
                : "border-accent !h-[150px]"
            }
          />
          {apiErrors.description && (
            <p className="text-sm text-destructive">
              {apiErrors.description[0]}
            </p>
          )}
        </div>

        {/* Created/Updated dates for view/edit mode */}
        {organizationType && (mode === "view" || mode === "edit") && (
          <div className="grid grid-cols-2 gap-4 pt-2">
            <div className="space-y-2">
              <Label className="text-muted-foreground">Created</Label>
              <p className="text-sm">
                {new Date(organizationType.created_at).toLocaleDateString(
                  "en-US",
                  {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  }
                )}
              </p>
            </div>
            <div className="space-y-2">
              <Label className="text-muted-foreground">Last Updated</Label>
              <p className="text-sm">
                {new Date(organizationType.updated_at).toLocaleDateString(
                  "en-US",
                  {
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                  }
                )}
              </p>
            </div>
          </div>
        )}
      </div>
    </form>
  );

  // Modal Footer
  const footer = isViewMode ? (
    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
      Close
    </Button>
  ) : (
    <div className="flex justify-end gap-2">
      <Button
        type="button"
        variant="outline"
        onClick={() => onOpenChange(false)}
        disabled={isLoading}
      >
        Cancel
      </Button>
      <Button
        type="submit"
        form="organization-type-form"
        disabled={isLoading || !formData.type_name || !formData.description}
      >
        {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
        {isCreateMode ? "Create Type" : "Update Type"}
      </Button>
    </div>
  );

  return (
    <ReusableModal
      open={open}
      onOpenChange={onOpenChange}
      title={title}
      body={body}
      footer={footer}
      className="sm:max-w-[500px]f"
    />
  );
}
