import { apiSlice, TAG_TYPES } from './apiSlice';

export interface Case {
  id: number;
  case_number: string;
  title: string;
  description: string | null;
  status: 'open' | 'active' | 'pending_settlement' | 'settled' | 'closed' | 'archived' | 'New' | 'Intake' | 'Active' | 'Demand' | 'Settlement' | 'Closed';
  accident_date: string | null;
  sol_date: string | null;
  jurisdiction: string | null;
  total_case_value: number;
  parties?: any[];
  timeline?: any[];
  created_by: {
    id: number;
    name: string;
  };
  organization_id: number;
  metadata: any;
  created_at: string;
  updated_at: string;
}

export interface CasesResponse {
  data: Case[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const casesApiSlice = apiSlice.enhanceEndpoints({
  addTagTypes: ['Case'],
}).injectEndpoints({
  endpoints: (builder) => ({
    getCases: builder.query<CasesResponse, { status?: string; search?: string; page?: number; per_page?: number }>({
      query: (params) => ({
        url: '/cases',
        params,
      }),
      providesTags: (result) =>
        result
          ? [
              ...result.data.map(({ id }) => ({ type: 'Case' as const, id })),
              { type: 'Case', id: 'LIST' },
            ]
          : [{ type: 'Case', id: 'LIST' }],
    }),
    getCaseById: builder.query<Case, number>({
      query: (id) => `/cases/${id}`,
      transformResponse: (response: any) => response.data || response,
      providesTags: (result, error, id) => [{ type: 'Case', id }],
    }),
    createCase: builder.mutation<Case, Partial<Case>>({
      query: (newCase) => ({
        url: '/cases',
        method: 'POST',
        body: newCase,
      }),
      invalidatesTags: [{ type: 'Case', id: 'LIST' }],
    }),
    updateCase: builder.mutation<Case, { id: number; data: Partial<Case> }>({
      query: ({ id, data }) => ({
        url: `/cases/${id}`,
        method: 'PUT',
        body: data,
      }),
      invalidatesTags: (result, error, { id }) => [{ type: 'Case', id }, { type: 'Case', id: 'LIST' }],
    }),
    deleteCase: builder.mutation<{ status: boolean; message: string }, number>({
      query: (id) => ({
        url: `/cases/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: [{ type: 'Case', id: 'LIST' }],
    }),
    addCaseParty: builder.mutation<any, { caseId: number; data: any }>({
      query: ({ caseId, data }) => ({
        url: `/cases/${caseId}/parties`,
        method: 'POST',
        body: data,
      }),
      invalidatesTags: (result, error, { caseId }) => [{ type: 'Case', id: caseId }],
    }),
    // Client-specific case endpoints
    getClientCases: builder.query<CasesResponse, { status?: string; search?: string; page?: number; per_page?: number }>({
      query: (params) => ({
        url: '/client/cases',
        params,
      }),
      providesTags: (result) =>
        result
          ? [
              ...result.data.map(({ id }) => ({ type: 'Case' as const, id })),
              { type: 'Case', id: 'LIST' },
            ]
          : [{ type: 'Case', id: 'LIST' }],
    }),
    getClientCaseById: builder.query<Case, number>({
      query: (id) => `/client/cases/${id}`,
      transformResponse: (response: any) => response.data || response,
      providesTags: (result, error, id) => [{ type: 'Case', id }],
    }),
  }),
});

export const {
  useGetCasesQuery,
  useGetCaseByIdQuery,
  useCreateCaseMutation,
  useUpdateCaseMutation,
  useDeleteCaseMutation,
  useAddCasePartyMutation,
  useGetClientCasesQuery,
  useGetClientCaseByIdQuery,
} = casesApiSlice;

