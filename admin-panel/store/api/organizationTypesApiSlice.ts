import { apiSlice, TAG_TYPES } from "./apiSlice";

// Organization Type Interface
export interface OrganizationType {
  id: number;
  type_name: string;
  description: string;
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface OrganizationTypesResponse {
  status: boolean;
  message: string;
  data: {
    organization_types: OrganizationType[];
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface OrganizationTypeResponse {
  status: boolean;
  message: string;
  data: OrganizationType;
}

export interface DeleteOrganizationTypeResponse {
  status: boolean;
  message: string;
}

// Request Types
export interface GetOrganizationTypesParams {
  page?: number;
  per_page?: number;
}

export interface CreateOrganizationTypeData {
  type_name: string;
  description: string;
}

export interface UpdateOrganizationTypeData {
  type_name?: string;
  description?: string;
}

// API Slice
export const organizationTypesApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Get all organization types with pagination
    getOrganizationTypes: builder.query<
      OrganizationTypesResponse,
      GetOrganizationTypesParams | void
    >({
      query: (params) => ({
        url: `/organization-types`,
        method: "GET",
        params: {
          page: params?.page || 1,
          per_page: params?.per_page || 10,
        },
      }),
      providesTags: (result) =>
        result
          ? [
            ...result.data.organization_types.map(({ id }) => ({
              type: TAG_TYPES.ORGANIZATION_TYPE,
              id,
            })),
            { type: TAG_TYPES.ORGANIZATION_TYPE, id: "LIST" },
          ]
          : [{ type: TAG_TYPES.ORGANIZATION_TYPE, id: "LIST" }],
    }),

    // Get organization type by ID
    getOrganizationTypeById: builder.query<OrganizationTypeResponse, number>({
      query: (id) => ({
        url: `/organization-types/${id}`,
        method: "GET",
      }),
      providesTags: (result, error, id) => [{ type: TAG_TYPES.ORGANIZATION_TYPE, id }],
    }),

    // Create new organization type
    createOrganizationType: builder.mutation<
      OrganizationTypeResponse,
      CreateOrganizationTypeData
    >({
      query: (data) => ({
        url: "/organization-types",
        method: "POST",
        body: data,
      }),
      invalidatesTags: [{ type: TAG_TYPES.ORGANIZATION_TYPE, id: "LIST" }],
    }),

    // Update organization type
    updateOrganizationType: builder.mutation<
      OrganizationTypeResponse,
      { id: number; data: UpdateOrganizationTypeData }
    >({
      query: ({ id, data }) => ({
        url: `/organization-types/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: TAG_TYPES.ORGANIZATION_TYPE, id },
        { type: TAG_TYPES.ORGANIZATION_TYPE, id: "LIST" },
      ],
    }),

    // Delete organization type
    deleteOrganizationType: builder.mutation<
      DeleteOrganizationTypeResponse,
      number
    >({
      query: (id) => ({
        url: `/organization-types/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: (result, error, id) => [
        { type: TAG_TYPES.ORGANIZATION_TYPE, id },
        { type: TAG_TYPES.ORGANIZATION_TYPE, id: "LIST" },
      ],
    }),
  }),
});

// Export hooks
export const {
  useGetOrganizationTypesQuery,
  useGetOrganizationTypeByIdQuery,
  useCreateOrganizationTypeMutation,
  useUpdateOrganizationTypeMutation,
  useDeleteOrganizationTypeMutation,
} = organizationTypesApiSlice;
