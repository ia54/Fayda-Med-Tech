"use client";

import React, { useState } from "react";
import { OrganizationForm } from "./OrganizationForm";
import type { OrganizationFormValues } from "@/lib/validations/organization";
import { Organization } from "@/store/api/organizationsApiSlice";
import { Button } from "@/components/ui/button";
import { ReusableModal } from "@/components/ui/reusable-modal";

interface OrganizationModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: "create" | "edit" | "view";
  organization?: Organization | null;
  onSubmit: (data: OrganizationFormValues) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
  errorMessage?: string;
}

export function OrganizationModal({
  open,
  onOpenChange,
  mode,
  organization,
  onSubmit,
  isLoading = false,
  apiErrors,
  errorMessage,
}: OrganizationModalProps) {
  const titles = {
    create: "Add New Organization",
    edit: "Edit Organization",
    view: "Organization Details",
  };

  const body =
    mode === "view" ? (
      <OrganizationDetails organization={organization} />
    ) : (
      <>
        {errorMessage && (
          <div className="mb-4 p-3 rounded-lg bg-destructive/10 border border-destructive/20">
            <p className="text-sm text-destructive font-medium">
              {errorMessage}
            </p>
          </div>
        )}
        <OrganizationForm
          organization={organization}
          onSubmit={onSubmit}
          onCancel={() => onOpenChange(false)}
          isLoading={isLoading}
          apiErrors={apiErrors}
        />
      </>
    );

  const footer =
    mode === "view" ? (
      <Button
        onClick={() => onOpenChange(false)}
        variant="destructive"
        size="lg"
      >
        Close
      </Button>
    ) : null;

  return (
    <ReusableModal
      open={open}
      onOpenChange={onOpenChange}
      title={titles[mode]}
      body={body}
      footer={footer}
    />
  );
}

function OrganizationDetails({
  organization,
}: {
  organization?: Organization | null;
}) {
  if (!organization) return <div>No organization data available.</div>;

  const details = [
    { label: "Organization Name", value: organization.org_name },
    { label: "Type", value: organization.org_type },
    { label: "Email", value: organization.email },
    { label: "Subscription Plan", value: organization.subscription_plan },
    {
      label: "Number of Employees",
      value: organization.no_of_employees?.toString() || "N/A",
    },
    {
      label: "Monthly Revenue",
      value: organization.monthly_revenue
        ? `$${organization.monthly_revenue.toLocaleString()}`
        : "N/A",
    },
    {
      label: "Yearly Revenue",
      value: organization.yearly_revenue
        ? `$${organization.yearly_revenue.toLocaleString()}`
        : "N/A",
    },
    { label: "Tax/BIN Number", value: organization.tax_bin_no || "N/A" },
    {
      label: "Created",
      value: new Date(organization.created_at).toLocaleDateString(),
    },
  ];

  return (
    <div className="space-y-4">
      {organization.company_logo_url && (
        <div className="flex justify-center">
          <img
            src={organization.company_logo_url}
            alt={organization.org_name}
            className="h-24 w-24 object-contain rounded-lg border"
          />
        </div>
      )}

      <div>
        <h2 className="font-semibold text-shadow-md">Support Documents</h2>
        {organization.support_documents_urls &&
        organization.support_documents_urls.length > 0 ? (
          <ul className="list-disc list-inside mb-4">
            {organization.support_documents_urls.map((url, index) => (
              <li key={index}>
                <a
                  href={url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue-600 underline"
                >
                  Document {index + 1}
                </a>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mb-4">No support documents available.</p>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {details.map((detail, index) => (
          <div key={index} className="p-3 rounded-lg border bg-muted/30">
            <p className="text-xs text-muted-foreground mb-1">{detail.label}</p>
            <p className="font-medium">{detail.value}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
