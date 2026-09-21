import { apiSlice } from './apiSlice';

export interface Lien {
  id: number;
  case_id: number;
  provider_id?: number;
  lien_type: 'medical' | 'attorney' | 'government_medicare' | 'government_medicaid' | 'health_insurance';
  amount: number;
  status: 'pending' | 'negotiated' | 'settled' | 'released';
  negotiated_amount?: number;
  reduction_amount?: number;
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
  endpoints: (builder) => ({
    getLiens: builder.query<{ data: { data: Lien[] } }, any>({
      query: (params) => ({
        url: '/liens',
        params,
      }),
      providesTags: ['Case'],
    }),
    createLien: builder.mutation<any, Partial<Lien>>({
      query: (body) => ({
        url: '/liens',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Case'],
    }),
    updateLien: builder.mutation<any, { id: number } & Partial<Lien>>({
      query: ({ id, ...body }) => ({
        url: `/liens/${id}`,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Case'],
    }),
    deleteLien: builder.mutation<any, number>({
      query: (id) => ({
        url: `/liens/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Case'],
    }),
  }),
});

export const {
  useGetLiensQuery,
  useCreateLienMutation,
  useUpdateLienMutation,
  useDeleteLienMutation,
} = liensApiSlice;
