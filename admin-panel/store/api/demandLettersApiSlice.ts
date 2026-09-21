import { apiSlice } from './apiSlice';

export interface DemandLetter {
  id: number;
  case_id: number;
  recipient_name: string;
  recipient_company?: string;
  recipient_address?: string;
  demand_amount: number;
  content?: string;
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'countered';
  sent_at?: string;
  response_date?: string;
  response_amount?: number;
  notes?: string;
  created_at: string;
  updated_at: string;
  case?: {
    id: number;
    title: string;
    case_number: string;
    status?: string;
  };
  creator?: {
    id: number;
    first_name: string;
    last_name: string;
  };
}

export const demandLettersApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getDemandLetters: builder.query<{ data: { data: DemandLetter[] } }, any>({
      query: (params) => ({
        url: '/demand-letters',
        params,
      }),
      providesTags: ['Case'],
    }),
    getDemandLetter: builder.query<{ data: DemandLetter }, number>({
      query: (id) => `/demand-letters/${id}`,
      providesTags: (result, error, id) => [{ type: 'Case' as const, id }],
    }),
    createDemandLetter: builder.mutation<any, Partial<DemandLetter>>({
      query: (body) => ({
        url: '/demand-letters',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Case'],
    }),
    updateDemandLetter: builder.mutation<any, { id: number } & Partial<DemandLetter>>({
      query: ({ id, ...body }) => ({
        url: `/demand-letters/${id}`,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Case'],
    }),
    deleteDemandLetter: builder.mutation<any, number>({
      query: (id) => ({
        url: `/demand-letters/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Case'],
    }),
  }),
});

export const {
  useGetDemandLettersQuery,
  useGetDemandLetterQuery,
  useCreateDemandLetterMutation,
  useUpdateDemandLetterMutation,
  useDeleteDemandLetterMutation,
} = demandLettersApiSlice;
