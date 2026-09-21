"use client";

import { useState, useEffect } from "react";
import { ReusableModal } from "@/components/ui/reusable-modal";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { AlertCircle, Loader2, Plus, X } from "lucide-react";
import { Alert, AlertDescription } from "@/components/ui/alert";
import type { SubscriptionPlan } from "@/store/api/subscriptionPlansApiSlice";
import type {
  SubscriptionPlanModalMode,
  SubscriptionPlanFormData,
} from "./useSubscriptionPlanModal";
import { SelectLabel } from "@radix-ui/react-select";

interface SubscriptionPlanModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: SubscriptionPlanModalMode;
  subscriptionPlan: SubscriptionPlan | null;
  onSubmit: (data: SubscriptionPlanFormData) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
  errorMessage?: string;
}

export function SubscriptionPlanModal({
  open,
  onOpenChange,
  mode,
  subscriptionPlan,
  onSubmit,
  isLoading = false,
  apiErrors = {},
  errorMessage = "",
}: SubscriptionPlanModalProps) {
  const [formData, setFormData] = useState<SubscriptionPlanFormData>({
    plan_name: "",
    price: 0,
    users: 0,
    features: [],
    organizations: 0,
    status: "active",
  });

  const [featureInput, setFeatureInput] = useState("");

  // Reset form when modal opens with data
  useEffect(() => {
    if (open && subscriptionPlan) {
      setFormData({
        plan_name: subscriptionPlan.plan_name,
        price: subscriptionPlan.price,
        users: subscriptionPlan.users,
        features: subscriptionPlan.features,
        organizations: subscriptionPlan.organizations,
        status: subscriptionPlan.status,
      });
    } else if (open && mode === "create") {
      setFormData({
        plan_name: "",
        price: 0,
        users: 0,
        features: [],
        organizations: 0,
        status: "active",
      });
    }
    setFeatureInput("");
  }, [open, subscriptionPlan, mode]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onSubmit(formData);
  };

  const handleAddFeature = () => {
    if (featureInput.trim()) {
      setFormData({
        ...formData,
        features: [...formData.features, featureInput.trim()],
      });
      setFeatureInput("");
    }
  };

  const handleRemoveFeature = (index: number) => {
    setFormData({
      ...formData,
      features: formData.features.filter((_, i) => i !== index),
    });
  };

  const isViewMode = mode === "view";
  const isCreateMode = mode === "create";

  const title =
    mode === "create"
      ? "Add Subscription Plan"
      : mode === "edit"
      ? "Edit Subscription Plan"
      : "View Subscription Plan";

  // Modal Body
  const body = (
    <form onSubmit={handleSubmit} id="subscription-plan-form">
      {/* Error Message */}
      {errorMessage && (
        <Alert variant="destructive" className="mb-4">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{errorMessage}</AlertDescription>
        </Alert>
      )}

      <div className="space-y-4">
        {/* Plan Name */}
        <div className="space-y-2">
          <Label htmlFor="plan_name">
            Plan Name <span className="text-destructive">*</span>
          </Label>

          <Select
            disabled={isViewMode || isLoading}
            defaultValue={formData.plan_name}
            onValueChange={(value) =>
              setFormData({ ...formData, plan_name: value })
            }
          >
            <SelectTrigger
              className={`w-full ${
                apiErrors.plan_name ? "!border-destructive" : ""
              }`}
              id="plan_name"
            >
              <SelectValue placeholder="Select a plan" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup id="plan_name">
                <SelectItem value="Basic">Basic</SelectItem>
                <SelectItem value="Enterprise">Enterprise</SelectItem>
                <SelectItem value="Premium">Premium</SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>

          {/* <Input
            id="plan_name"
            placeholder="e.g., Premium, Enterprise"
            value={formData.plan_name}
            onChange={(e) =>
              setFormData({ ...formData, plan_name: e.target.value })
            }
            disabled={isViewMode || isLoading}
            className={apiErrors.plan_name ? "border-destructive" : ""}
          /> */}
          {apiErrors.plan_name && (
            <p className="text-sm text-destructive">{apiErrors.plan_name[0]}</p>
          )}
        </div>

        {/* Price and Users */}
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="price">
              Price <span className="text-destructive">*</span>
            </Label>
            <Input
              id="price"
              type="number"
              step="0.01"
              min="0"
              placeholder="0.00"
              value={formData.price}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  price: parseFloat(e.target.value) || 0,
                })
              }
              disabled={isViewMode || isLoading}
              className={apiErrors.price ? "border-destructive" : ""}
            />
            {apiErrors.price && (
              <p className="text-sm text-destructive">{apiErrors.price[0]}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="users">
              Users <span className="text-destructive">*</span>
            </Label>
            <Input
              id="users"
              type="number"
              min="1"
              placeholder="50"
              value={formData.users}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  users: parseInt(e.target.value) || 0,
                })
              }
              disabled={isViewMode || isLoading}
              className={apiErrors.users ? "border-destructive" : ""}
            />
            {apiErrors.users && (
              <p className="text-sm text-destructive">{apiErrors.users[0]}</p>
            )}
          </div>
        </div>

        {/* Organizations and Status */}
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="organizations">
              Organizations <span className="text-destructive">*</span>
            </Label>
            <Input
              id="organizations"
              type="number"
              min="0"
              placeholder="5"
              value={formData.organizations}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  organizations: parseInt(e.target.value) || 0,
                })
              }
              disabled={isViewMode || isLoading}
              className={apiErrors.organizations ? "border-destructive" : ""}
            />
            {apiErrors.organizations && (
              <p className="text-sm text-destructive">
                {apiErrors.organizations[0]}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="status">
              Status <span className="text-destructive">*</span>
            </Label>
            <Select
              value={formData.status}
              onValueChange={(value: "active" | "inactive") =>
                setFormData({ ...formData, status: value })
              }
              disabled={isViewMode || isLoading}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="inactive">Inactive</SelectItem>
              </SelectContent>
            </Select>
            {apiErrors.status && (
              <p className="text-sm text-destructive">{apiErrors.status[0]}</p>
            )}
          </div>
        </div>

        {/* Features */}
        <div className="space-y-2">
          <Label htmlFor="features">
            Features <span className="text-destructive">*</span>
          </Label>

          {!isViewMode && (
            <div className="flex gap-2">
              <Input
                id="feature-input"
                placeholder="Add a feature..."
                value={featureInput}
                onChange={(e) => setFeatureInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    handleAddFeature();
                  }
                }}
                disabled={isLoading}
              />
              <Button
                type="button"
                size="sm"
                onClick={handleAddFeature}
                disabled={isLoading || !featureInput.trim()}
              >
                <Plus className="h-4 w-4" />
              </Button>
            </div>
          )}

          {/* Features List */}
          {formData.features.length > 0 && (
            <div className="space-y-2 mt-3">
              {formData.features.map((feature, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between bg-muted p-2 rounded-md"
                >
                  <span className="text-sm">{feature}</span>
                  {!isViewMode && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => handleRemoveFeature(index)}
                      disabled={isLoading}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              ))}
            </div>
          )}

          {apiErrors.features && (
            <p className="text-sm text-destructive">{apiErrors.features[0]}</p>
          )}
        </div>

        {/* Created/Updated dates for view/edit mode */}
        {subscriptionPlan && (mode === "view" || mode === "edit") && (
          <div className="grid grid-cols-2 gap-4 pt-2">
            <div className="space-y-2">
              <Label className="text-muted-foreground">Created</Label>
              <p className="text-sm">
                {new Date(subscriptionPlan.created_at).toLocaleDateString(
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
                {new Date(subscriptionPlan.updated_at).toLocaleDateString(
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
        form="subscription-plan-form"
        disabled={
          isLoading ||
          !formData.plan_name ||
          formData.price <= 0 ||
          formData.users <= 0 ||
          formData.features.length === 0
        }
      >
        {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
        {isCreateMode ? "Create Plan" : "Update Plan"}
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
      className="sm:max-w-[600px]"
    />
  );
}
