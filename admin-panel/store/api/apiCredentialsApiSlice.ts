import { apiSlice, TAG_TYPES } from './apiSlice';

export interface ApiCredential {
  id: number;
  provider: string;
  name: string;
  is_active: boolean;
  metadata?: any;
  created_at: string;
  updated_at: string;
}

export type ApiCredentialInput = Partial<Pick<ApiCredential, 'provider' | 'name' | 'is_active' | 'metadata'>> & { key?: string; value?: string };

export interface ApiCredentialsResponse {
  status: boolean;
  message: string;
  data: ApiCredential[];
}

export interface ApiCredentialResponse {
  status: boolean;
  message: string;
  data: ApiCredential;
}

export const apiCredentialsApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getApiCredentials: builder.query<ApiCredentialsResponse, void>({
      query: () => '/api-credentials',
      providesTags: (result) =>
        result
          ? [
              ...result.data.map(({ id }) => ({ type: TAG_TYPES.API_CREDENTIALS, id } as const)),
              { type: TAG_TYPES.API_CREDENTIALS, id: 'LIST' },
            ]
          : [{ type: TAG_TYPES.API_CREDENTIALS, id: 'LIST' }],
    }),
    getApiCredential: builder.query<ApiCredentialResponse, number>({
      query: (id) => `/api-credentials/${id}`,
      providesTags: (result, error, id) => [{ type: TAG_TYPES.API_CREDENTIALS, id }],
    }),
    createApiCredential: builder.mutation<ApiCredentialResponse, ApiCredentialInput>({
      query: (credential) => ({
        url: '/api-credentials',
        method: 'POST',
        body: credential,
      }),
      invalidatesTags: [{ type: TAG_TYPES.API_CREDENTIALS, id: 'LIST' }],
    }),
    updateApiCredential: builder.mutation<ApiCredentialResponse, { id: number; data: ApiCredentialInput }>({
      query: ({ id, data }) => ({
        url: `/api-credentials/${id}`,
        method: 'PUT',
        body: data,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: TAG_TYPES.API_CREDENTIALS, id },
        { type: TAG_TYPES.API_CREDENTIALS, id: 'LIST' },
      ],
    }),
    deleteApiCredential: builder.mutation<{ status: boolean; message: string }, number>({
      query: (id) => ({
        url: `/api-credentials/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: [{ type: TAG_TYPES.API_CREDENTIALS, id: 'LIST' }],
    }),
  }),
});

export const {
  useGetApiCredentialsQuery,
  useGetApiCredentialQuery,
  useCreateApiCredentialMutation,
  useUpdateApiCredentialMutation,
  useDeleteApiCredentialMutation,
} = apiCredentialsApiSlice;
