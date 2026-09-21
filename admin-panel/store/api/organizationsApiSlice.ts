import { apiSlice, TAG_TYPES } from "./apiSlice";

// Organization Type
export interface Organization {
  id: number;
  org_name: string;
  org_type: string;
  subscription_plan: string;
  email: string;
  no_of_employees: number | null;
  monthly_revenue: number | null;
  yearly_revenue: number | null;
  company_logo: string | null;
  company_logo_url: string | null;
  tax_bin_no: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  support_documents: string[];
  support_documents_urls: string[];
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface OrganizationsResponse {
  status: boolean;
  message: string;
  data: {
    organizations: Organization[];
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface OrganizationResponse {
  status: boolean;
  message: string;
  data: Organization;
}

export interface DeleteOrganizationResponse {
  status: boolean;
  message: string;
}

// Request Types
export interface GetOrganizationsParams {
  page?: number;
  per_page?: number;
  org_name?: string;
  org_type?: string;
  subscription_plan?: string;
}

export interface CreateOrganizationData {
  org_name: string;
  org_type: string;
  subscription_plan: string;
  email: string;
  no_of_employees?: number;
  monthly_revenue?: number;
  yearly_revenue?: number;
  company_logo?: File;
  tax_bin_no?: string;
  support_documents?: File[];
}

export interface UpdateOrganizationData extends Partial<CreateOrganizationData> {
  _method?: "PUT";
}

// API Slice
export const organizationsApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Get all organizations with pagination
    getOrganizations: builder.query<OrganizationsResponse, GetOrganizationsParams>({
      query: ({ page = 1, per_page = 10, org_name, org_type, subscription_plan }) => ({
        url: "/organizations",
        method: "GET",
        params: {
          page,
          per_page,
          ...(org_name ? { org_name } : {}),
          ...(org_type ? { org_type } : {}),
          ...(subscription_plan ? { subscription_plan } : {}),
        },
      }),
      providesTags: (result) =>
        result
          ? [
            ...result.data.organizations.map(({ id }) => ({
              type: TAG_TYPES.ORGANIZATION,
              id,
            })),
            { type: TAG_TYPES.ORGANIZATION, id: "LIST" },
          ]
          : [{ type: TAG_TYPES.ORGANIZATION, id: "LIST" }],
    }),

    // Get organization by ID
    getOrganizationById: builder.query<OrganizationResponse, number>({
      query: (id) => ({
        url: `/organizations/${id}`,
        method: "GET",
      }),
      providesTags: (result, error, id) => [{ type: TAG_TYPES.ORGANIZATION, id }],
    }),

    // Create new organization
    createOrganization: builder.mutation<OrganizationResponse, FormData>({
      query: (formData) => ({
        url: "/organizations",
        method: "POST",
        body: formData,
      }),
      invalidatesTags: [{ type: TAG_TYPES.ORGANIZATION, id: "LIST" }],
    }),

    // Update organization
    updateOrganization: builder.mutation<
      OrganizationResponse,
      { id: number; formData: FormData }
    >({
      query: ({ id, formData }) => ({
        url: `/organizations/${id}`,
        method: "POST",
        body: formData,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: TAG_TYPES.ORGANIZATION, id },
        { type: TAG_TYPES.ORGANIZATION, id: "LIST" },
      ],
    }),

    // Delete organization
    deleteOrganization: builder.mutation<DeleteOrganizationResponse, number>({
      query: (id) => ({
        url: `/organizations/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: (result, error, id) => [
        { type: TAG_TYPES.ORGANIZATION, id },
        { type: TAG_TYPES.ORGANIZATION, id: "LIST" },
      ],
    }),
  }),
});

// Export hooks
export const {
  useGetOrganizationsQuery,
  useGetOrganizationByIdQuery,
  useCreateOrganizationMutation,
  useUpdateOrganizationMutation,
  useDeleteOrganizationMutation,
} = organizationsApiSlice;

// Helper function to create FormData from organization data
export function createOrganizationFormData(
  data: CreateOrganizationData | UpdateOrganizationData,
  isUpdate: boolean = false
): FormData {
  const formData = new FormData();

  // Add all fields to FormData
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && key !== "_method") {
      if (key === "company_logo" && value instanceof File) {
        formData.append(key, value);
      } else if (key === "support_documents" && Array.isArray(value)) {
        value.forEach((file) => {
          if (file instanceof File) {
            formData.append("support_documents[]", file);
          }
        });
      } else {
        formData.append(key, String(value));
      }
    }
  });

  // Add _method=PUT for updates (must be added AFTER other fields for Laravel)
  if (isUpdate) {
    formData.append("_method", "PUT");
  }

  return formData;
}
