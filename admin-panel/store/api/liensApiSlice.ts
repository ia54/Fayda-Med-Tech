import { apiSlice } from './apiSlice';

export interface Lien {
  id: number;
  case_id: number;
  provider_id?: number | null;
  lien_type: 'medical' | 'attorney' | 'government_medicare' | 'government_medicaid' | 'health_insurance';
  amount: number | string;
  status: 'pending' | 'negotiated' | 'settled' | 'released';
  negotiated_amount?: number | string | null;
  reduction_amount?: number | string | null;
  payoff_date?: string;
  release_document_url?: string;
  notes?: string;
  organization_id: number;
  created_at: string;
  updated_at: string;
  case?: { id: number; title: string; case_number: string; status?: string };
  provider?: { id: number; name: string };
}

export const liensApiSlice = apiSlice.injectEndpoints({
  overrideExisting: true,
  endpoints: (builder) => ({
    getLienProviderOptions: builder.query<{ data: { id: number; name: string }[] }, { search: string }>({
      query: params => ({ url: '/liens/provider-options', params }),
      providesTags: ['Provider'],
    }),
    getLiens: builder.query<{ data: { data: Lien[]; total: number; last_page: number; next_page_url: string | null } }, any>({
      query: (params) => ({
        url: '/liens',
        params,
      }),
      providesTags: ['Lien', 'Case'],
    }),
    createLien: builder.mutation<any, Partial<Lien>>({
      query: (body) => ({
        url: '/liens',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Lien', 'Case'],
    }),
    updateLien: builder.mutation<any, { id: number } & Partial<Lien>>({
      query: ({ id, ...body }) => ({
        url: `/liens/${id}`,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Lien', 'Case'],
    }),
    deleteLien: builder.mutation<any, number>({
      query: (id) => ({
        url: `/liens/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Lien', 'Case'],
    }),
  }),
});

export const {
  useGetLienProviderOptionsQuery,
  useGetLiensQuery,
  useCreateLienMutation,
  useUpdateLienMutation,
  useDeleteLienMutation,
} = liensApiSlice;
