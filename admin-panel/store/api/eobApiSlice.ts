import { apiSlice } from './apiSlice';

export interface Eob {
  id: number;
  case_id?: number;
  document_id?: number;
  invoice_id?: number;
  provider_name: string;
  patient_name: string;
  payer_name: string;
  billed_amount: number;
  allowed_amount: number;
  paid_amount: number;
  patient_responsibility: number;
  service_date?: string;
  eob_date?: string;
  ai_confidence?: number;
  status: 'pending' | 'processed' | 'matched' | 'rejected';
  extracted_data?: any;
  notes?: string;
  created_at: string;
  updated_at: string;
  case?: { id: number; title: string; case_number: string };
  uploader?: { id: number; first_name: string; last_name: string };
}

export interface EobStats {
  total_processed: number;
  pending_review: number;
  avg_confidence: number;
  total_paid_amount: number;
}

export const eobApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getEobs: builder.query<{ data: { data: Eob[] } }, any>({
      query: (params) => ({
        url: '/eobs',
        params,
      }),
      providesTags: ['Invoice'],
    }),
    getEobStats: builder.query<{ data: EobStats }, void>({
      query: () => '/eobs/stats',
      providesTags: ['Invoice'],
    }),
    createEob: builder.mutation<any, Partial<Eob>>({
      query: (body) => ({
        url: '/eobs',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Invoice'],
    }),
    updateEob: builder.mutation<any, { id: number } & Partial<Eob>>({
      query: ({ id, ...body }) => ({
        url: `/eobs/${id}`,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Invoice'],
    }),
    deleteEob: builder.mutation<any, number>({
      query: (id) => ({
        url: `/eobs/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Invoice'],
    }),
  }),
});

export const {
  useGetEobsQuery,
  useGetEobStatsQuery,
  useCreateEobMutation,
  useUpdateEobMutation,
  useDeleteEobMutation,
} = eobApiSlice;
