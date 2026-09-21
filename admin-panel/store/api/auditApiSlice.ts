import { apiSlice } from './apiSlice';

export interface AuditLog {
  id: number;
  user_id: number;
  organization_id: number;
  event: string;
  auditable_type: string | null;
  auditable_id: number | null;
  old_values: any;
  new_values: any;
  url: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
  };
  organization?: {
    id: number;
    org_name: string;
  };
}

export interface AuditLogsResponse {
  status: boolean;
  message: string;
  data: AuditLog[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export const auditApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getAuditLogs: builder.query<AuditLogsResponse, { 
      page?: number; 
      per_page?: number; 
      search?: string;
      event?: string;
      organization_id?: number;
      from_date?: string;
      to_date?: string;
    }>({
      query: (params) => ({
        url: '/admin/audit-logs',
        params,
      }),
      providesTags: ['AuditLog' as any],
    }),
    getAuditLogById: builder.query<{ status: boolean; data: AuditLog }, number>({
      query: (id) => `/admin/audit-logs/${id}`,
      providesTags: (result, error, id) => [{ type: 'AuditLog' as any, id }],
    }),
  }),
});

export const {
  useGetAuditLogsQuery,
  useGetAuditLogByIdQuery,
} = auditApiSlice;
