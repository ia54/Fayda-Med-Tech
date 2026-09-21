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
import {
  Upload,
  X,
  Building2,
  DollarSign,
  FileText,
  Loader2,
} from "lucide-react";
import { LoadingButton } from "@/components/ui/loading-button";
import { Organization } from "@/store/api/organizationsApiSlice";
import { useGetOrganizationTypesQuery } from "@/store/api/organizationTypesApiSlice";
import { useGetSubscriptionPlansQuery } from "@/store/api/subscriptionPlansApiSlice";
import { type OrganizationFormValues } from "@/lib/validations/organization";

interface OrganizationFormProps {
  organization?: Organization | null;
  onSubmit: (data: OrganizationFormValues) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
}

export function OrganizationForm({
  organization,
  onSubmit,
  onCancel,
  isLoading = false,
  apiErrors,
}: OrganizationFormProps) {
  console.log(organization, "organization");

  // Fetch organization types and subscription plans
  const { data: orgTypesResponse, isLoading: isLoadingOrgTypes } =
    useGetOrganizationTypesQuery({
      page: 1,
      per_page: 100, // Get all types
    });

  const { data: plansResponse, isLoading: isLoadingPlans } =
    useGetSubscriptionPlansQuery({
      page: 1,
      per_page: 100, // Get all plans
      status: "active", // Only active plans
    });

  const organizationTypes = orgTypesResponse?.data?.organization_types || [];
  const subscriptionPlans = plansResponse?.data?.subscription_plans || [];

  const [formData, setFormData] = useState({
    org_name: organization?.org_name || "",
    org_type: organization?.org_type || "",
    subscription_plan: organization?.subscription_plan || "",
    email: organization?.email || "",
    no_of_employees: organization?.no_of_employees || "",
    monthly_revenue: organization?.monthly_revenue || "",
    yearly_revenue: organization?.yearly_revenue || "",
    tax_bin_no: organization?.tax_bin_no || "",
  });

  console.log(formData, "formData");

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
  const [companyLogo, setCompanyLogo] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(
    organization?.company_logo_url || null
  );

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const maxSize = 5 * 1024 * 1024;
      const acceptedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/webp",
      ];

      if (file.size > maxSize) {
        setErrors((prev) => ({
          ...prev,
          company_logo: "Max file size is 5MB",
        }));
        return;
      }

      if (!acceptedTypes.includes(file.type)) {
        setErrors((prev) => ({
          ...prev,
          company_logo:
            "Only .jpg, .jpeg, .png and .webp formats are supported",
        }));
        return;
      }

      setErrors((prev) => ({ ...prev, company_logo: "" }));
      setCompanyLogo(file);

      const reader = new FileReader();
      reader.onloadend = () => setPreviewUrl(reader.result as string);
      reader.readAsDataURL(file);
    }
  };

  const [existingSupportDocs, setExistingSupportDocs] = useState<string[]>(
    organization?.support_documents_urls || []
  );

  const [newSupportDocs, setNewSupportDocs] = useState<File[]>([]);

  // ────────────────────────────────────────────────
  // In handleDocsChange:
  const handleDocsChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    const maxSize = 5 * 1024 * 1024;
    const acceptedTypes = [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ];

    for (const file of files) {
      if (file.size > maxSize) {
        setErrors((prev) => ({
          ...prev,
          support_documents: `${file.name} exceeds 5MB`,
        }));
        return;
      }
      if (!acceptedTypes.includes(file.type)) {
        setErrors((prev) => ({
          ...prev,
          support_documents: `${file.name} is not a supported format`,
        }));
        return;
      }
    }

    const updatedNewDocs = [...newSupportDocs, ...files];
    if (updatedNewDocs.length > 5) {
      setErrors((prev) => ({
        ...prev,
        support_documents: "Maximum 5 new documents allowed",
      }));
      return;
    }

    setErrors((prev) => ({ ...prev, support_documents: "" }));
    setNewSupportDocs(updatedNewDocs);
  };

  // ────────────────────────────────────────────────
  // Remove document (only affects new uploads)
  const removeNewDoc = (index: number) => {
    setNewSupportDocs(newSupportDocs.filter((_, i) => i !== index));
    setErrors((prev) => ({ ...prev, support_documents: "" }));
  };

  const removeLogo = () => {
    setCompanyLogo(null);
    setPreviewUrl(null);
    setErrors((prev) => ({ ...prev, company_logo: "" }));
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.org_name.trim()) newErrors.org_name = "Required";
    if (!formData.org_type) newErrors.org_type = "Required";
    if (!formData.email.trim()) newErrors.email = "Required";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email))
      newErrors.email = "Invalid email";
    if (!formData.subscription_plan) newErrors.subscription_plan = "Required";

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmitForm = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateForm()) return;

    const submitData: OrganizationFormValues = {
      ...formData,
      no_of_employees: formData.no_of_employees
        ? Number(formData.no_of_employees)
        : undefined,
      monthly_revenue: formData.monthly_revenue
        ? Number(formData.monthly_revenue)
        : undefined,
      yearly_revenue: formData.yearly_revenue
        ? Number(formData.yearly_revenue)
        : undefined,
      company_logo: companyLogo || undefined,
      support_documents: newSupportDocs.length > 0 ? newSupportDocs : undefined,
    } as any;

    await onSubmit(submitData);
  };

  return (
    <form onSubmit={handleSubmitForm} className="space-y-6">
      {/* Basic Information Section */}
      <div className="space-y-4">
        <div className="flex items-center gap-2 text-primary">
          <Building2 className="h-5 w-5" />
          <h3 className="text-lg font-semibold">Basic Information</h3>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Organization Name */}
          <div className="space-y-2">
            <Label htmlFor="org_name">
              Organization Name <span className="text-destructive">*</span>
            </Label>
            <Input
              id="org_name"
              value={formData.org_name}
              onChange={(e) =>
                setFormData((prev) => ({ ...prev, org_name: e.target.value }))
              }
              placeholder="Enter organization name"
              disabled={isLoading}
            />
            {displayErrors.org_name && (
              <p className="text-sm text-destructive">
                {displayErrors.org_name}
              </p>
            )}
          </div>

          {/* Organization Type */}
          <div className="space-y-2">
            <Label htmlFor="org_type">
              Organization Type <span className="text-destructive">*</span>
            </Label>
            <Select
              value={formData.org_type}
              onValueChange={(value) =>
                setFormData((prev) => ({ ...prev, org_type: value }))
              }
              disabled={isLoading || isLoadingOrgTypes}
            >
              <SelectTrigger>
                <SelectValue
                  placeholder={
                    isLoadingOrgTypes ? "Loading types..." : "Select type"
                  }
                />
              </SelectTrigger>
              <SelectContent>
                {isLoadingOrgTypes ? (
                  <div className="flex items-center justify-center p-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                  </div>
                ) : organizationTypes.length > 0 ? (
                  organizationTypes.map((type) => (
                    <SelectItem key={type.id} value={type.type_name}>
                      {type.type_name}
                    </SelectItem>
                  ))
                ) : (
                  <div className="p-2 text-sm text-muted-foreground text-center">
                    No types available
                  </div>
                )}
              </SelectContent>
            </Select>
            {displayErrors.org_type && (
              <p className="text-sm text-destructive">
                {displayErrors.org_type}
              </p>
            )}
          </div>

          {/* Email */}
          <div className="space-y-2">
            <Label htmlFor="email">
              Email <span className="text-destructive">*</span>
            </Label>
            <Input
              id="email"
              type="email"
              value={formData.email}
              onChange={(e) =>
                setFormData((prev) => ({ ...prev, email: e.target.value }))
              }
              placeholder="organization@example.com"
              disabled={isLoading}
            />
            {displayErrors.email && (
              <p className="text-sm text-destructive">{displayErrors.email}</p>
            )}
          </div>

          {/* Subscription Plan */}
          <div className="space-y-2">
            <Label htmlFor="subscription_plan">
              Subscription Plan <span className="text-destructive">*</span>
            </Label>
            <Select
              value={formData.subscription_plan}
              onValueChange={(value) =>
                setFormData((prev) => ({ ...prev, subscription_plan: value }))
              }
              disabled={isLoading || isLoadingPlans}
            >
              <SelectTrigger>
                <SelectValue
                  placeholder={
                    isLoadingPlans ? "Loading plans..." : "Select plan"
                  }
                />
              </SelectTrigger>
              <SelectContent>
                {isLoadingPlans ? (
                  <div className="flex items-center justify-center p-2">
                    <Loader2 className="h-4 w-4 animate-spin" />
                  </div>
                ) : subscriptionPlans.length > 0 ? (
                  subscriptionPlans.map((plan) => (
                    <SelectItem key={plan.id} value={plan.plan_name}>
                      <div className="flex items-center justify-between gap-4">
                        <span>{plan.plan_name}</span>
                        <span className="text-xs text-muted-foreground">
                          ${Number(plan.price).toFixed(2)}/month
                        </span>
                      </div>
                    </SelectItem>
                  ))
                ) : (
                  <div className="p-2 text-sm text-muted-foreground text-center">
                    No plans available
                  </div>
                )}
              </SelectContent>
            </Select>
            {displayErrors.subscription_plan && (
              <p className="text-sm text-destructive">
                {displayErrors.subscription_plan}
              </p>
            )}
          </div>
        </div>
      </div>

      {/* Financial Information Section */}
      <div className="space-y-4">
        <div className="flex items-center gap-2 text-primary">
          <DollarSign className="h-5 w-5" />
          <h3 className="text-lg font-semibold">Financial Information</h3>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Number of Employees */}
          <div className="space-y-2">
            <Label htmlFor="no_of_employees">Number of Employees</Label>
            <Input
              id="no_of_employees"
              type="number"
              value={formData.no_of_employees}
              onChange={(e) =>
                setFormData((prev) => ({
                  ...prev,
                  no_of_employees: e.target.value,
                }))
              }
              placeholder="50"
              disabled={isLoading}
            />
            {displayErrors.no_of_employees && (
              <p className="text-sm text-destructive">
                {displayErrors.no_of_employees}
              </p>
            )}
          </div>

          {/* Monthly Revenue */}
          <div className="space-y-2">
            <Label htmlFor="monthly_revenue">Monthly Revenue ($)</Label>
            <Input
              id="monthly_revenue"
              type="number"
              step="0.01"
              value={formData.monthly_revenue}
              onChange={(e) =>
                setFormData((prev) => ({
                  ...prev,
                  monthly_revenue: e.target.value,
                }))
              }
              placeholder="50000"
              disabled={isLoading}
            />
            {displayErrors.monthly_revenue && (
              <p className="text-sm text-destructive">
                {displayErrors.monthly_revenue}
              </p>
            )}
          </div>

          {/* Yearly Revenue */}
          <div className="space-y-2">
            <Label htmlFor="yearly_revenue">Yearly Revenue ($)</Label>
            <Input
              id="yearly_revenue"
              type="number"
              step="0.01"
              value={formData.yearly_revenue}
              onChange={(e) =>
                setFormData((prev) => ({
                  ...prev,
                  yearly_revenue: e.target.value,
                }))
              }
              placeholder="600000"
              disabled={isLoading}
            />
            {displayErrors.yearly_revenue && (
              <p className="text-sm text-destructive">
                {displayErrors.yearly_revenue}
              </p>
            )}
          </div>
        </div>

        {/* Tax/BIN Number */}
        <div className="space-y-2">
          <Label htmlFor="tax_bin_no">Tax/BIN Number</Label>
          <Input
            id="tax_bin_no"
            value={formData.tax_bin_no}
            onChange={(e) =>
              setFormData((prev) => ({ ...prev, tax_bin_no: e.target.value }))
            }
            placeholder="123456789"
            disabled={isLoading}
          />
          {displayErrors.tax_bin_no && (
            <p className="text-sm text-destructive">
              {displayErrors.tax_bin_no}
            </p>
          )}
        </div>
      </div>

      {/* Documents Section */}
      <div className="space-y-4">
        <div className="flex items-center gap-2 text-primary">
          <FileText className="h-5 w-5" />
          <h3 className="text-lg font-semibold">Documents</h3>
        </div>

        {/* Company Logo */}
        <div className="space-y-2">
          <Label>Company Logo</Label>
          <p className="text-xs text-muted-foreground">
            Accepted formats: JPG, PNG, WEBP (Max 5MB)
          </p>
          {previewUrl && (
            <div className="relative inline-block">
              <img
                src={previewUrl}
                alt="Company logo preview"
                className="h-24 w-24 object-cover rounded-md border"
              />
              <Button
                type="button"
                variant="destructive"
                size="sm"
                className="absolute -top-2 -right-2 h-6 w-6 rounded-full p-0"
                onClick={removeLogo}
                disabled={isLoading}
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          )}
          <div className="flex items-center gap-2">
            <Input
              id="company_logo"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              onChange={handleLogoChange}
              className="hidden"
              disabled={isLoading}
            />
            <Button
              type="button"
              variant="outline"
              onClick={() => document.getElementById("company_logo")?.click()}
              disabled={isLoading}
            >
              <Upload className="h-4 w-4 mr-2" />
              {previewUrl ? "Change Logo" : "Upload Logo"}
            </Button>
          </div>
          {displayErrors.company_logo && (
            <p className="text-sm text-destructive">
              {displayErrors.company_logo}
            </p>
          )}
        </div>

        {/* Support Documents */}
        <div className="space-y-2">
          <Label>Support Documents</Label>
          <p className="text-xs text-muted-foreground">
            Accepted: PDF, DOC, DOCX (max 5 new files, 5MB each)
          </p>

          {/* Existing documents (view only) */}
          {existingSupportDocs.length > 0 && (
            <div className="space-y-2 mb-3">
              <p className="text-xs font-medium text-muted-foreground">
                Current documents:
              </p>
              {existingSupportDocs.map((url, index) => (
                <div
                  key={url}
                  className="flex items-center justify-between p-2 border rounded-md bg-muted/40"
                >
                  <a
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm text-primary hover:underline truncate flex-1"
                  >
                    Document {index + 1}
                  </a>
                  {/* Optional: add remove button if backend supports deleting existing docs */}
                </div>
              ))}
            </div>
          )}

          {/* Newly uploaded documents */}
          {newSupportDocs.length > 0 && (
            <div className="space-y-2">
              <p className="text-xs font-medium text-muted-foreground">
                New uploads:
              </p>
              {newSupportDocs.map((doc, index) => (
                <div
                  key={index}
                  className="flex items-center justify-between p-2 border rounded-md"
                >
                  <span className="text-sm truncate flex-1">{doc.name}</span>
                  <span className="text-xs text-muted-foreground mr-2">
                    {(doc.size / 1024).toFixed(1)} KB
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => removeNewDoc(index)}
                    disabled={isLoading}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              ))}
            </div>
          )}

          <div className="flex items-center gap-2">
            <Input
              id="support_documents"
              type="file"
              multiple
              accept=".pdf,.doc,.docx"
              onChange={handleDocsChange}
              className="hidden"
              disabled={isLoading || newSupportDocs.length >= 5}
            />
            <Button
              type="button"
              variant="outline"
              onClick={() =>
                document.getElementById("support_documents")?.click()
              }
              disabled={isLoading || newSupportDocs.length >= 5}
            >
              <Upload className="h-4 w-4 mr-2" />
              Upload New Documents
            </Button>
          </div>

          {displayErrors.support_documents && (
            <p className="text-sm text-destructive">
              {displayErrors.support_documents}
            </p>
          )}
        </div>
      </div>

      {/* Form Actions */}
      <div className="flex justify-end gap-2 pt-4 border-t">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isLoading}
        >
          Cancel
        </Button>
        <LoadingButton
          type="submit"
          loading={isLoading}
          loadingText={organization ? "Updating..." : "Creating..."}
        >
          {organization ? "Update" : "Create"} Organization
        </LoadingButton>
      </div>
    </form>
  );
}
