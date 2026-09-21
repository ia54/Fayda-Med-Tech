import { apiSlice, TAG_TYPES } from './apiSlice';

// TypeScript Interfaces
export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  role: 'admin' | 'firm_admin' | 'attorney' | 'medical_biller' | 'provider_staff' | 'client';
  organization: string | null;
  organization_id: number | null;
  organization_relation?: {
    id: number;
    org_name: string;
    company_logo_url?: string | null;
    support_documents_urls?: string[];
  };
  status: 'active' | 'inactive';
  last_login: string | null;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface UserFormData {
  first_name: string;
  last_name: string;
  email: string;
  password?: string;
  organization_id: number;
  role: 'admin' | 'firm_admin' | 'attorney' | 'medical_biller' | 'provider_staff' | 'client';
  status: 'active' | 'inactive';
  send_email?: boolean;
}

export interface UsersResponse {
  status: boolean;
  message: string;
  data: {
    users: User[];
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface UserResponse {
  status: boolean;
  message: string;
  data: User;
}

export interface CreateUserResponse {
  status: boolean;
  message: string;
  data: {
    user: User;
    email_sent: boolean;
  };
}

export interface DeleteUserResponse {
  status: boolean;
  message: string;
}

export interface GetUsersParams {
  page?: number;
  per_page?: number;
  role?: 'admin' | 'firm_admin' | 'attorney' | 'medical_biller' | 'provider_staff' | 'client' | 'all';
  status?: 'active' | 'inactive';
  search?: string;
}

// Inject endpoints into API slice
export const usersApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // GET /api/admin/users - Get all users
    getUsers: builder.query<UsersResponse, GetUsersParams>({
      query: ({ page = 1, per_page = 10, role, status, search } = {}) => {
        const params = new URLSearchParams({
          page: page.toString(),
          per_page: per_page.toString(),
        });
        if (role) {
          params.append('role', role);
        }
        if (status) {
          params.append('status', status);
        }
        if (search) {
          params.append('name', search);
        }
        return `/admin/users?${params.toString()}`;
      },
      providesTags: (result) =>
        result
          ? [
            ...result.data.users.map(({ id }) => ({
              type: TAG_TYPES.USER,
              id,
            })),
            { type: TAG_TYPES.USER, id: 'LIST' },
          ]
          : [{ type: TAG_TYPES.USER, id: 'LIST' }],
    }),

    // GET /api/admin/users/{id} - Get a specific user
    getUserById: builder.query<UserResponse, number>({
      query: (id) => `/admin/users/${id}`,
      providesTags: (result, error, id) => [{ type: TAG_TYPES.USER, id }],
    }),

    // POST /api/admin/users - Create a new user
    createUser: builder.mutation<CreateUserResponse, UserFormData>({
      query: (userData) => ({
        url: '/admin/users',
        method: 'POST',
        body: userData,
      }),
      invalidatesTags: [{ type: TAG_TYPES.USER, id: 'LIST' }],
    }),

    // PUT /api/admin/users/{id} - Update a user
    updateUser: builder.mutation<UserResponse, { id: number; data: Partial<UserFormData> }>({
      query: ({ id, data }) => ({
        url: `/admin/users/${id}`,
        method: 'PUT',
        body: data,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: TAG_TYPES.USER, id },
        { type: TAG_TYPES.USER, id: 'LIST' },
      ],
    }),

    // DELETE /api/admin/users/{id} - Delete a user
    deleteUser: builder.mutation<DeleteUserResponse, number>({
      query: (id) => ({
        url: `/admin/users/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: [{ type: TAG_TYPES.USER, id: 'LIST' }],
    }),
  }),
});

// Export hooks for usage in components
export const {
  useGetUsersQuery,
  useGetUserByIdQuery,
  useCreateUserMutation,
  useUpdateUserMutation,
  useDeleteUserMutation,
} = usersApiSlice;
