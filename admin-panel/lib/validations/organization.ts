import * as z from "zod";

export const ORG_TYPES = [
  "Healthcare Provider",
  "Hospital",
  "Clinic",
  "Pharmacy",
  "Laboratory",
  "Insurance Company",
  "Other",
] as const;

export const SUBSCRIPTION_PLANS = [
  "Free",
  "Basic",
  "Premium",
  "Enterprise",
] as const;

export const organizationSchema = z.object({
  org_name: z.string().min(1, "Required"),
  org_type: z.enum(ORG_TYPES, { error: issue => issue.input === undefined ? "Required" : undefined }),
  subscription_plan: z.enum(SUBSCRIPTION_PLANS, { error: issue => issue.input === undefined ? "Required" : undefined }),
  email: z.string().min(1, "Required").email("Invalid email"),
  no_of_employees: z.number().optional(),
  monthly_revenue: z.number().optional(),
  yearly_revenue: z.number().optional(),
  tax_bin_no: z.string().optional(),
  company_logo: z.instanceof(File).optional(),
  support_documents: z.array(z.instanceof(File)).optional(),
});

export type OrganizationFormValues = z.infer<typeof organizationSchema>;
