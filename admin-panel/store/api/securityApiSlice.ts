import { apiSlice } from "./apiSlice"

export const securityApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getSecuritySettings: builder.query({
      query: () => "/admin/security/settings",
      providesTags: ["Security"],
    }),
    updateSecuritySettings: builder.mutation({
      query: (body) => ({
        url: "/admin/security/settings",
        method: "PUT",
        body,
      }),
      invalidatesTags: ["Security"],
    }),
    getSecurityStats: builder.query({
      query: () => "/admin/security/stats",
      providesTags: ["SecurityStats"],
    }),
    getSecurityEvents: builder.query({
      query: () => "/admin/security/events",
      providesTags: ["SecurityEvents"],
    }),
    getIpAllowlist: builder.query({
      query: () => "/admin/security/ip-allowlist",
      providesTags: ["IpAllowlist"],
    }),
    addIpToAllowlist: builder.mutation({
      query: (body) => ({
        url: "/admin/security/ip-allowlist",
        method: "POST",
        body,
      }),
      invalidatesTags: ["IpAllowlist"],
    }),
    updateIpAllowlist: builder.mutation({
      query: ({ id, ...body }) => ({
        url: `/admin/security/ip-allowlist/${id}`,
        method: "PUT",
        body,
      }),
      invalidatesTags: ["IpAllowlist"],
    }),
    deleteIpFromAllowlist: builder.mutation({
      query: (id) => ({
        url: `/admin/security/ip-allowlist/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: ["IpAllowlist"],
    }),
  }),
})

export const {
  useGetSecuritySettingsQuery,
  useUpdateSecuritySettingsMutation,
  useGetSecurityStatsQuery,
  useGetSecurityEventsQuery,
  useGetIpAllowlistQuery,
  useAddIpToAllowlistMutation,
  useUpdateIpAllowlistMutation,
  useDeleteIpFromAllowlistMutation,
} = securityApiSlice
