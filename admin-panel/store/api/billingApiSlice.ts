import { apiSlice } from './apiSlice';

export interface BillingStats {
  work_queue: number;
  appeals_generated: number;
  claims_processed: number;
  success_rate: string;
}

export interface WorkQueueItem {
  id: string;
  patient: string;
  issue: string;
  priority: string;
  payer: string;
  amount: string;
  assigned: string;
  type: string;
}

export interface BillingDashboardResponse {
  status: boolean;
  message: string;
  data: {
    stats: BillingStats;
    work_queue_preview: WorkQueueItem[];
    recent_appeals: any[];
    denial_reasons: { reason: string; percentage: number }[];
    payer_performance: { payer: string; rate: number; color: string }[];
  };
}

export interface Invoice {
  id: number;
  invoice_number: string;
  total_paid?: number | string | null;
  amount: number;
  status: 'draft' | 'sent' | 'paid' | 'denied' | 'voided';
  due_date: string;
  created_at?: string;
  notes?: string;
  paid_at?: string;
  metadata?: { billing_review?: { state: 'reviewed' | 'returned'; note: string; reviewed_by: number; reviewed_at: string }; payer?: string; cpt_codes?: string; diagnosis_codes?: string; patient_name?: string; service_date?: string; notes?: string };
  case_id?: number;
  case?: {
    id: number;
    title: string;
    case_number?: string;
  };
}

export interface Payment {
  notes?: string;
  recorded_by?: number;
  reversal_of_id?: number | null;
  reversal?: { id: number; notes?: string } | null;
  id: number;
  invoice_id: number;
  amount: number;
  payment_method: string;
  transaction_id?: string;
  payment_date: string;
  invoice?: Invoice;
}

export interface Appeal {
  id: number;
  appeal_number: string;
  reason_category: string;
  content: string;
  status: 'draft' | 'sent' | 'accepted' | 'rejected';
  created_at: string;
  invoice?: Invoice;
}

export interface ValidationIssue {
  id: number;
  issue_type: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  description: string;
  status: 'pending' | 'in_review' | 'resolved' | 'ignored';
  created_at: string;
  invoice?: Invoice;
}

export const billingApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getBillingStats: builder.query<BillingDashboardResponse, void>({
      query: () => '/billing/stats',
      providesTags: ['Invoice'],
    }),
    getInvoices: builder.query<{ data: { data: Invoice[]; current_page: number; last_page: number; total: number } }, any>({
      query: (params) => ({
        url: '/invoices',
        params,
      }),
      providesTags: ['Invoice'],
    }),
    getPayments: builder.query<{ data: { data: Payment[]; current_page: number; last_page: number; total: number } }, any>({
      query: (params) => ({
        url: '/payments',
        params,
      }),
      providesTags: ['Payment'],
    }),
    getAppeals: builder.query<{ data: { data: Appeal[] } }, any>({
      query: (params) => ({
        url: '/billing/appeals',
        params,
      }),
      providesTags: ['Invoice'],
    }),
    getValidationIssues: builder.query<{ data: { issues: { data: ValidationIssue[] }, stats: any } }, any>({
      query: (params) => ({
        url: '/billing/validation',
        params,
      }),
      providesTags: ['Invoice'],
    }),
    getBillingAnalytics: builder.query<any, void>({
      query: () => '/billing/analytics',
      providesTags: ['Invoice', 'Payment'],
    }),
    reversePayment: builder.mutation<any, { id: number; reason: string }>({
      query: ({ id, reason }) => ({ url: `/payments/${id}/reverse`, method: 'POST', body: { reason } }),
      invalidatesTags: ['Payment', 'Invoice'],
    }),
    createPayment: builder.mutation<any, Partial<Payment>>({
      query: (body) => ({
        url: '/payments',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Payment', 'Invoice'],
    }),
    createInvoice: builder.mutation<any, Partial<Invoice>>({
      query: (body) => ({
        url: '/invoices',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Invoice'],
    }),
    updateProviderDraft: builder.mutation<{ data: Invoice }, { id: number; amount: number; status: 'draft' | 'sent'; metadata: Invoice['metadata'] }>({
      query: ({ id, ...body }) => ({ url: `/provider/invoices/${id}/draft`, method: 'PUT', body }),
      invalidatesTags: ['Invoice'],
    }),
    reviewInvoice: builder.mutation<{ data: Invoice }, { id: number; action: 'reviewed' | 'return'; note: string }>({
      query: ({ id, ...body }) => ({ url: `/invoices/${id}/review`, method: 'POST', body }),
      invalidatesTags: ['Invoice'],
    }),
    deleteInvoice: builder.mutation<any, number>({
      query: (id) => ({
        url: `/invoices/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Invoice'],
    }),
    getDocuments: builder.query<any, any>({
      query: (params) => ({
        url: '/documents',
        params,
      }),
      providesTags: ['Document'],
    }),
    uploadDocument: builder.mutation<any, FormData>({
      query: (body) => ({
        url: '/documents',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Document'],
    }),
    generateAppeal: builder.mutation<any, { invoice_id: number; reason_category: string; additional_details?: string }>({
      query: (body) => ({
        url: '/billing/appeals/generate',
        method: 'POST',
        body,
      }),
    }),
    getProviderStats: builder.query<any, void>({
      query: () => '/provider/stats',
      providesTags: ['Invoice'],
    }),
    getClientStats: builder.query<any, void>({
      query: () => '/client/stats',
      providesTags: ['Invoice', 'Document'],
    }),
    // Client-specific endpoints
    getClientInvoices: builder.query<{ data: { data: Invoice[] } }, any>({
      query: (params) => ({
        url: '/client/invoices',
        params,
      }),
      providesTags: ['Invoice'],
    }),
    getClientInvoiceDetail: builder.query<any, number>({
      query: (id) => `/client/invoices/${id}`,
      providesTags: (result, error, id) => [{ type: 'Invoice' as const, id }],
    }),
    getClientPayments: builder.query<{ data: { data: Payment[] } }, any>({
      query: (params) => ({
        url: '/client/payments',
        params,
      }),
      providesTags: ['Payment'],
    }),
    createClientPayment: builder.mutation<any, Partial<Payment>>({
      query: (body) => ({
        url: '/client/payments',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Payment', 'Invoice'],
    }),
    getClientPendingSignatures: builder.query<any, any>({
      query: (params) => ({
        url: '/client/signatures/pending',
        params,
      }),
      providesTags: ['Document'],
    }),
    getClientDocuments: builder.query<any, any>({
      query: (params) => ({
        url: '/client/documents',
        params,
      }),
      providesTags: ['Document'],
    }),
  }),
});

export const {
  useGetBillingStatsQuery,
  useGetInvoicesQuery,
  useGetPaymentsQuery,
  useGetAppealsQuery,
  useGetValidationIssuesQuery,
  useGetBillingAnalyticsQuery,
  useGetDocumentsQuery,
  useUploadDocumentMutation,
  useCreateInvoiceMutation,
  useReviewInvoiceMutation,
  useUpdateProviderDraftMutation,
  useDeleteInvoiceMutation,
  useCreatePaymentMutation,
  useReversePaymentMutation,
  useGenerateAppealMutation,
  useGetProviderStatsQuery,
  useGetClientStatsQuery,
  useGetClientInvoicesQuery,
  useGetClientInvoiceDetailQuery,
  useGetClientPaymentsQuery,
  useCreateClientPaymentMutation,
  useGetClientPendingSignaturesQuery,
  useGetClientDocumentsQuery,
} = billingApiSlice;

