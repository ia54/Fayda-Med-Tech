import { apiSlice, TAG_TYPES } from './apiSlice';
import { Organization } from './organizationsApiSlice';

export interface FirmStats {
  active_cases: number;
  total_recovery: number;
  pending_amount: number;
  settlements_ready: number;
}

export interface RecentActivity {
  id: number;
  user: string;
  event: string;
  description: string;
  timestamp: string;
}

export interface FirmDashboardResponse {
  status: boolean;
  message: string;
  data: {
    stats: FirmStats;
    recent_activity: RecentActivity[];
    revenue_data: { month: string; amount: number }[];
    team_workload: { name: string; cases: number }[];
    recovery_growth: string | null;
    financial_basis: string;
  };
}

export interface FirmOrganizationResponse {
  status: boolean;
  message: string;
  data: Organization;
}

export const firmApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getFirmStats: builder.query<FirmDashboardResponse, void>({
      query: () => '/firm/stats',
      providesTags: ['Case'], // Invalidate stats when cases change
    }),
    getFirmOrganization: builder.query<FirmOrganizationResponse, void>({
      query: () => '/firm/organization',
      providesTags: (result) => 
        result ? [{ type: TAG_TYPES.ORGANIZATION, id: result.data.id }] : [TAG_TYPES.ORGANIZATION],
    }),
    updateFirmOrganization: builder.mutation<FirmOrganizationResponse, FormData>({
      query: (formData) => ({
        url: '/firm/organization',
        method: 'POST', // Using POST for file uploads
        body: formData,
      }),
      invalidatesTags: (result) => 
        result ? [{ type: TAG_TYPES.ORGANIZATION, id: result.data.id }, { type: TAG_TYPES.ORGANIZATION, id: 'LIST' }] : [TAG_TYPES.ORGANIZATION],
    }),
  }),
});

export const {
  useGetFirmStatsQuery,
  useGetFirmOrganizationQuery,
  useUpdateFirmOrganizationMutation,
} = firmApiSlice;
