import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import type { BaseQueryFn, FetchArgs, FetchBaseQueryError } from '@reduxjs/toolkit/query';
import { logout, setCredentials } from '../slices/authSlice';
import { sessionIdentity } from '../session-boundary.mjs';
import type { RootState } from '../store';

export interface Notification {
  id: string;
  read_at: string | null;
  created_at: string;
  data: {
    title: string;
    message: string;
    type: string;
    action_url?: string | null;
  };
}

interface NotificationsResponse {
  status: boolean;
  message: string;
  data: {
    notifications: Notification[];
    unread_count: number;
    pagination: { current_page: number; last_page: number; total: number };
  };
}

type NotificationUpdateResponse = { status: boolean; message: string };

// API Base URL
const API_BASE_URL = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';

// Tag Types for RTK Query Cache Invalidation
export const TAG_TYPES = {
  AUTH: 'Auth',
  PHARMACY: 'Pharmacy',
  ORGANIZATION: 'Organization',
  ORGANIZATION_TYPE: 'OrganizationType',
  SUBSCRIPTION_PLAN: 'SubscriptionPlan',
  USER: 'User',
  ROLE: 'Role',
  BLOG: 'Blog',
  BLOG_CATEGORY: 'BlogCategory',
  CMS: 'CMS',
  TESTIMONIALS: 'Testimonials',
  FAQ: 'FAQ',
  WHY_WE_DIFFERENT: 'WhyWeAreDifferent',
  DOCUMENT: 'Document',
  SIGNATURE: 'Signature',
  OCR_RESULT: 'OcrResult',
  API_CREDENTIALS: 'ApiCredentials',
  CASE: 'Case',
  AUDIT_LOG: 'AuditLog',
  PERMISSION: 'Permission',
  SECURITY: 'Security',
  SECURITY_STATS: 'SecurityStats',
  SECURITY_EVENTS: 'SecurityEvents',
  IP_ALLOWLIST: 'IpAllowlist',
  INVOICE: 'Invoice',
  PAYMENT: 'Payment',
  NOTIFICATION: 'Notification',
  INSURANCE_COMPANY: 'InsuranceCompany',
  INSURANCE_CLAIM: 'InsuranceClaim',
  PROVIDER: 'Provider',
  LIEN: 'Lien',
  TREATMENT_RECORD: 'TreatmentRecord',
  LETTER_OF_PROTECTION: 'LetterOfProtection',
  SETTLEMENT: 'Settlement',
  MEDICAL_HISTORY: 'MedicalHistory',
  HIPAA_AUTH: 'HipaaAuthorization',
  DOCUMENT_CATEGORY: 'DocumentCategory',
  MEDICAL_RECORD_REQUEST: 'MedicalRecordRequest',
  CLIENT_PORTAL_SETTING: 'ClientPortalSetting',
  REPORT: 'Report',
} as const;

export type TagType = typeof TAG_TYPES[keyof typeof TAG_TYPES];

// Create a baseQuery that doesn't automatically add auth headers
const rawBaseQuery = fetchBaseQuery({
  baseUrl: API_BASE_URL,
  credentials: 'include',
});

type RefreshPayload = { access_token: string; refresh_token: string; token_type: string; expires_in: number };
const refreshesInFlight = new Map<string, Promise<RefreshPayload | null>>();

// Custom baseQuery that adds auth headers selectively and handles 401 errors
const baseQueryWithErrorHandling: BaseQueryFn<string | FetchArgs, unknown, FetchBaseQueryError> = async (
  args,
  api,
  extraOptions
) => {
  // Determine if this is an auth endpoint
  const requestIdentity = sessionIdentity((api.getState() as RootState).auth);
  const staleSession = () => !isAuthEndpoint && requestIdentity !== sessionIdentity((api.getState() as RootState).auth);
  const sessionChanged = () => ({ error: { status: 'CUSTOM_ERROR' as const, error: 'Session changed. Discarded prior account response.' } });
  const url = typeof args === "string" ? args : args.url;
  // Detect method
  const method =
    typeof args === "string"
      ? "GET"
      : (args.method ? args.method.toUpperCase() : "GET");
  const isAuthEndpoint = url.includes("/login") || url.includes("/register") || url.includes("/refresh-token") || url.includes("/get-setting-values") || (method === "GET" && url.includes("/whywedifferent"));

  // Add headers selectively
  const headers = new Headers();

  // Preserve existing headers from args
  if (typeof args !== "string" && args.headers) {
    const existingHeaders = args.headers instanceof Headers
      ? args.headers
      : new Headers(args.headers as HeadersInit);
    existingHeaders.forEach((value, key) => {
      headers.set(key, value);
    });
  }

  // Set Accept header if not already set
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }

  // Only add Authorization header for non-auth endpoints
  if (!isAuthEndpoint) {
    const token = (api.getState() as RootState).auth.token?.access_token;
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }

  // Prepare arguments with headers
  const modifiedArgs = typeof args === "string" ? { url: args, headers } : { ...args, headers };

  let result = await rawBaseQuery(modifiedArgs, api, extraOptions);

  if (staleSession()) return sessionChanged();

  // Serialize refreshes: single-use refresh tokens must not race across parallel requests.
  if (result.error?.status === 401 && !isAuthEndpoint) {
    const state = (api.getState() as RootState).auth;
    if (state.token?.access_token && headers.get('Authorization') !== `Bearer ${state.token.access_token}`) {
      headers.set('Authorization', `Bearer ${state.token.access_token}`);
      result = await rawBaseQuery({ ...modifiedArgs, headers }, api, extraOptions);
    } else if (state.token?.refresh_token) {
      const refreshToken = state.token.refresh_token;
      let refreshInFlight = refreshesInFlight.get(refreshToken);
      if (!refreshInFlight) {
        refreshInFlight = (async () => {
          const refreshed = await rawBaseQuery({ url: '/refresh-token', method: 'POST', body: { refresh_token: refreshToken } }, api, extraOptions);
          const data = refreshed.data as RefreshPayload | undefined;
          if (!data?.access_token || !data.refresh_token) return null;
          const current = (api.getState() as RootState).auth;
          // Never resurrect a signed-out or switched account.
          if (!current.user || sessionIdentity(current) !== requestIdentity || current.token?.refresh_token !== refreshToken) return null;
          api.dispatch(setCredentials({ token: data, user: current.user }));
          return data;
        })().finally(() => { refreshesInFlight.delete(refreshToken); });
        refreshesInFlight.set(refreshToken, refreshInFlight);
      }
      const refreshed = await refreshInFlight;
      if (staleSession()) return sessionChanged();
      if (refreshed) {
        headers.set('Authorization', `Bearer ${refreshed.access_token}`);
        result = await rawBaseQuery({ ...modifiedArgs, headers }, api, extraOptions);
      } else {
        api.dispatch(logout());
        if (typeof window !== 'undefined') window.location.href = '/auth/login';
      }
    } else {
      api.dispatch(logout());
      if (typeof window !== 'undefined') window.location.href = '/auth/login';
    }
  }

  if (staleSession()) return sessionChanged();
  return result;
};

export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithErrorHandling,
  tagTypes: Object.values(TAG_TYPES),
  endpoints: (builder) => ({
    // ========= Notifications (PDF Section 14) =========
    getNotifications: builder.query<NotificationsResponse, { page?: number }>({
      query: ({ page = 1 }) => ({
        url: '/notifications',
        params: { page },
      }),
      providesTags: (result) =>
        result
          ? [
              ...result.data.notifications.map(({ id }) => ({ type: 'Notification' as const, id })),
              { type: 'Notification' as const, id: 'LIST' },
            ]
          : [{ type: 'Notification' as const, id: 'LIST' }],
    }),
    markAsRead: builder.mutation<NotificationUpdateResponse, string>({
      query: (id) => ({
        url: `/notifications/${id}/read`,
        method: 'POST',
      }),
      invalidatesTags: (result, error, id) => error ? [] : [
        { type: 'Notification' as const, id },
        { type: 'Notification' as const, id: 'LIST' },
      ],
    }),
    markAllAsRead: builder.mutation<NotificationUpdateResponse, void>({
      query: () => ({
        url: '/notifications/read-all',
        method: 'POST',
      }),
      invalidatesTags: (result, error) => error ? [] : [{ type: 'Notification' as const, id: 'LIST' }],
    }),

    // ========= Insurance Management (PDF Section 7) =========
    getInsuranceCompanies: builder.query({
      query: (params = {}) => ({
        url: "/insurance/companies",
        params,
      }),
      providesTags: ["InsuranceCompany"],
    }),
    createInsuranceCompany: builder.mutation({
      query: (data) => ({
        url: "/insurance/companies",
        method: "POST",
        body: data,
      }),
      invalidatesTags: ["InsuranceCompany"],
    }),
    updateInsuranceCompany: builder.mutation({
      query: ({ id, ...data }) => ({
        url: `/insurance/companies/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: ["InsuranceCompany"],
    }),
    deleteInsuranceCompany: builder.mutation({
      query: (id) => ({
        url: `/insurance/companies/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: ["InsuranceCompany"],
    }),
    getInsuranceCorrespondence: builder.query({
      query: (params) => ({ url: "/insurance/correspondence", params }),
      providesTags: ["InsuranceClaim"],
    }),
    createInsuranceCorrespondence: builder.mutation({
      query: (body) => ({ url: "/insurance/correspondence", method: "POST", body }),
      invalidatesTags: ["InsuranceClaim"],
    }),
    getInsuranceClaims: builder.query({
      query: (params = {}) => ({
        url: "/insurance/claims",
        params,
      }),
      providesTags: ["InsuranceClaim"],
    }),
    createInsuranceClaim: builder.mutation({
      query: (data) => ({
        url: "/insurance/claims",
        method: "POST",
        body: data,
      }),
      invalidatesTags: ["InsuranceClaim"],
    }),

    // ========= Provider & Lien Management (PDF Section 10) =========
    getProviders: builder.query({
      query: (params = {}) => ({
        url: "/providers",
        params,
      }),
      providesTags: ["Provider"],
    }),
    createProvider: builder.mutation({
      query: (data) => ({
        url: "/providers",
        method: "POST",
        body: data,
      }),
      invalidatesTags: ["Provider"],
    }),
    updateProvider: builder.mutation({
      query: ({ id, ...data }) => ({
        url: `/providers/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: ["Provider"],
    }),
    deleteProvider: builder.mutation({
      query: (id) => ({
        url: `/providers/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: ["Provider"],
    }),
    getLiens: builder.query({
      query: (params = {}) => ({
        url: "/liens",
        params,
      }),
      providesTags: ["Lien"],
    }),
    createLien: builder.mutation({
      query: (data) => ({
        url: "/liens",
        method: "POST",
        body: data,
      }),
      invalidatesTags: ["Lien"],
    }),
    updateLien: builder.mutation({
      query: ({ id, ...data }) => ({
        url: `/liens/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: ["Lien"],
    }),
    deleteLien: builder.mutation({
      query: (id) => ({
        url: `/liens/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: ["Lien"],
    }),

    // ========= Case Settlements (Attorney specific) =========
    getSettlements: builder.query({
      query: (params = {}) => ({
        url: "/settlements",
        params,
      }),
      providesTags: ["Settlement" as any],
    }),
    createSettlement: builder.mutation({
      query: (data) => ({
        url: "/settlements",
        method: "POST",
        body: data,
      }),
      invalidatesTags: ["Settlement" as any, "Case", "Report"],
    }),
    correctSettlement: builder.mutation({
      query: ({ id, ...data }) => ({ url: `/settlements/${id}/corrections`, method: "POST", body: data }),
      invalidatesTags: ["Settlement" as any, "Case", "Report"],
    }),
    updateSettlement: builder.mutation({
      query: ({ id, ...data }) => ({
        url: `/settlements/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: ["Settlement" as any, "Case", "Report"],
    }),
    deleteSettlement: builder.mutation({
      query: (id) => ({
        url: `/settlements/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: ["Settlement" as any, "Case", "Report"],
    }),

    // ===== NEW: Medical History (PDF Section 5) =====
    getMedicalHistories: builder.query<any, any>({
      query: (params = {}) => ({ url: '/medical-histories', params }),
      providesTags: ['MedicalHistory'],
    }),
    createMedicalHistory: builder.mutation<any, any>({
      query: (data) => ({ url: '/medical-histories', method: 'POST', body: data }),
      invalidatesTags: ['MedicalHistory'],
    }),
    updateMedicalHistory: builder.mutation<any, { id: number; [key: string]: any }>({
      query: ({ id, ...data }) => ({ url: `/medical-histories/${id}`, method: 'PUT', body: data }),
      invalidatesTags: ['MedicalHistory'],
    }),
    deleteMedicalHistory: builder.mutation<any, number>({
      query: (id) => ({ url: `/medical-histories/${id}`, method: 'DELETE' }),
      invalidatesTags: ['MedicalHistory'],
    }),

    // ===== NEW: HIPAA Authorizations (PDF Section 5) =====
    getHipaaAuthorizations: builder.query<any, any>({
      query: (params = {}) => ({ url: '/hipaa-authorizations', params }),
      providesTags: ['HipaaAuthorization'],
    }),
    createHipaaAuthorization: builder.mutation<any, any>({
      query: (data) => ({ url: '/hipaa-authorizations', method: 'POST', body: data }),
      invalidatesTags: ['HipaaAuthorization'],
    }),
    updateHipaaAuthorization: builder.mutation<any, { id: number; [key: string]: any }>({
      query: ({ id, ...data }) => ({ url: `/hipaa-authorizations/${id}`, method: 'PUT', body: data }),
      invalidatesTags: ['HipaaAuthorization'],
    }),
    deleteHipaaAuthorization: builder.mutation<any, number>({
      query: (id) => ({ url: `/hipaa-authorizations/${id}`, method: 'DELETE' }),
      invalidatesTags: ['HipaaAuthorization'],
    }),

    // ===== NEW: Document Categories (PDF Section 8) =====
    getDocumentCategories: builder.query({
      query: (params = {}) => ({ url: '/document-categories', params }),
      providesTags: ['DocumentCategory'],
    }),
    createDocumentCategory: builder.mutation({
      query: (data) => ({ url: '/document-categories', method: 'POST', body: data }),
      invalidatesTags: ['DocumentCategory'],
    }),
    updateDocumentCategory: builder.mutation({
      query: ({ id, ...data }) => ({ url: `/document-categories/${id}`, method: 'PUT', body: data }),
      invalidatesTags: ['DocumentCategory'],
    }),
    deleteDocumentCategory: builder.mutation({
      query: (id) => ({ url: `/document-categories/${id}`, method: 'DELETE' }),
      invalidatesTags: ['DocumentCategory'],
    }),
    assignDocumentCategories: builder.mutation({
      query: ({ id, category_ids }) => ({ url: `/documents/${id}/categories`, method: 'POST', body: { category_ids } }),
      invalidatesTags: ['Document', 'DocumentCategory'],
    }),

    // ===== NEW: Medical Record Requests (PDF Section 8) =====
    getMedicalRecordRequests: builder.query({
      query: (params = {}) => ({ url: '/medical-record-requests', params }),
      providesTags: ['MedicalRecordRequest'],
    }),
    createMedicalRecordRequest: builder.mutation({
      query: (data) => ({ url: '/medical-record-requests', method: 'POST', body: data }),
      invalidatesTags: ['MedicalRecordRequest'],
    }),
    updateMedicalRecordRequest: builder.mutation({
      query: ({ id, ...data }) => ({ url: `/medical-record-requests/${id}`, method: 'PUT', body: data }),
      invalidatesTags: ['MedicalRecordRequest'],
    }),
    deleteMedicalRecordRequest: builder.mutation({
      query: (id) => ({ url: `/medical-record-requests/${id}`, method: 'DELETE' }),
      invalidatesTags: ['MedicalRecordRequest'],
    }),

    // ===== NEW: Client Portal Settings (PDF Section 5) =====
    getClientPortalSettings: builder.query({
      query: (params = {}) => ({ url: '/client-portal-settings', params }),
      providesTags: ['ClientPortalSetting'],
    }),
    getClientPortalSettingByUser: builder.query({
      query: (userId) => ({ url: `/client-portal-settings/${userId}` }),
      providesTags: (result, error, userId) => [{ type: 'ClientPortalSetting', id: userId }],
    }),
    updateClientPortalSetting: builder.mutation({
      query: ({ userId, ...data }) => ({ url: `/client-portal-settings/${userId}`, method: 'PUT', body: data }),
      invalidatesTags: (result, error, { userId }) => [{ type: 'ClientPortalSetting', id: userId }],
    }),

    // ===== NEW: Reports (PDF Section 11 - All 14 Reports) =====
    getCaseStatusReport: builder.query({
      query: (params = {}) => ({ url: '/reports/case-status', params }),
      providesTags: ['Report'],
    }),
    getRevenueByPeriodReport: builder.query({
      query: (params = {}) => ({ url: '/reports/revenue-by-period', params }),
      providesTags: ['Report'],
    }),
    getInsuranceAgingReport: builder.query({
      query: (params = {}) => ({ url: '/reports/insurance-aging', params }),
      providesTags: ['Report'],
    }),
    getSettlementSummaryReport: builder.query({
      query: (params = {}) => ({ url: '/reports/settlement-summary', params }),
      providesTags: ['Report'],
    }),
    getAttorneyProductionReport: builder.query({
      query: (params = {}) => ({ url: '/reports/attorney-production', params }),
      providesTags: ['Report'],
    }),
    getProviderBillingReport: builder.query({
      query: (params = {}) => ({ url: '/reports/provider-billing', params }),
      providesTags: ['Report'],
    }),
    getLienSummaryReport: builder.query({
      query: (params = {}) => ({ url: '/reports/lien-summary', params }),
      providesTags: ['Report'],
    }),
    getOcrProcessingLogReport: builder.query({
      query: (params = {}) => ({ url: '/reports/ocr-processing-log', params }),
      providesTags: ['Report'],
    }),
    getSignatureActivityReport: builder.query({
      query: (params = {}) => ({ url: '/reports/signature-activity', params }),
      providesTags: ['Report'],
    }),
    getUserActivityLog: builder.query({
      query: (params = {}) => ({ url: '/reports/user-activity-log', params }),
      providesTags: ['Report'],
    }),
    getDocumentAuditTrail: builder.query({
      query: (params = {}) => ({ url: '/reports/document-audit-trail', params }),
      providesTags: ['Report'],
    }),
    getHipaaComplianceLog: builder.query({
      query: (params = {}) => ({ url: '/reports/hipaa-compliance-log', params }),
      providesTags: ['Report'],
    }),
    getCollectionRateReport: builder.query({
      query: (params = {}) => ({ url: '/reports/collection-rate', params }),
      providesTags: ['Report'],
    }),
    getReferralSourceReport: builder.query({
      query: (params = {}) => ({ url: '/reports/referral-source', params }),
      providesTags: ['Report'],
    }),
    getReportHistory: builder.query({
      query: (params = {}) => ({ url: '/reports/history', params }),
      providesTags: ['Report'],
    }),
    getReportById: builder.query({
      query: (id) => ({ url: `/reports/${id}` }),
      providesTags: (result, error, id) => [{ type: 'Report', id }],
    }),
  }),
});

// Export hooks for all endpoints
export const {
  // Notifications
  useGetNotificationsQuery,
  useMarkAsReadMutation,
  useMarkAllAsReadMutation,
  // Insurance
  useGetInsuranceCompaniesQuery,
  useCreateInsuranceCompanyMutation,
  useUpdateInsuranceCompanyMutation,
  useDeleteInsuranceCompanyMutation,
  useGetInsuranceCorrespondenceQuery,
  useCreateInsuranceCorrespondenceMutation,
  useGetInsuranceClaimsQuery,
  useCreateInsuranceClaimMutation,
  // Providers
  useGetProvidersQuery,
  useCreateProviderMutation,
  useUpdateProviderMutation,
  useDeleteProviderMutation,
  // Liens
  useGetLiensQuery,
  useCreateLienMutation,
  useUpdateLienMutation,
  useDeleteLienMutation,
  // Settlements
  useGetSettlementsQuery,
  useCreateSettlementMutation,
  useUpdateSettlementMutation,
  useCorrectSettlementMutation,
  useDeleteSettlementMutation,
  // Medical History
  useGetMedicalHistoriesQuery,
  useCreateMedicalHistoryMutation,
  useUpdateMedicalHistoryMutation,
  useDeleteMedicalHistoryMutation,
  // HIPAA Authorizations
  useGetHipaaAuthorizationsQuery,
  useCreateHipaaAuthorizationMutation,
  useUpdateHipaaAuthorizationMutation,
  useDeleteHipaaAuthorizationMutation,
  // Document Categories
  useGetDocumentCategoriesQuery,
  useCreateDocumentCategoryMutation,
  useUpdateDocumentCategoryMutation,
  useDeleteDocumentCategoryMutation,
  useAssignDocumentCategoriesMutation,
  // Medical Record Requests
  useGetMedicalRecordRequestsQuery,
  useCreateMedicalRecordRequestMutation,
  useUpdateMedicalRecordRequestMutation,
  useDeleteMedicalRecordRequestMutation,
  // Client Portal Settings
  useGetClientPortalSettingsQuery,
  useGetClientPortalSettingByUserQuery,
  useUpdateClientPortalSettingMutation,
  // Reports
  useGetCaseStatusReportQuery,
  useGetRevenueByPeriodReportQuery,
  useGetInsuranceAgingReportQuery,
  useGetSettlementSummaryReportQuery,
  useGetAttorneyProductionReportQuery,
  useGetProviderBillingReportQuery,
  useGetLienSummaryReportQuery,
  useGetOcrProcessingLogReportQuery,
  useGetSignatureActivityReportQuery,
  useGetUserActivityLogQuery,
  useGetDocumentAuditTrailQuery,
  useGetHipaaComplianceLogQuery,
  useGetCollectionRateReportQuery,
  useGetReferralSourceReportQuery,
  useGetReportHistoryQuery,
  useGetReportByIdQuery,
} = apiSlice;