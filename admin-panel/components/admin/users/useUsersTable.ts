import { useState, useMemo } from 'react';
import { useToast } from '@/hooks/use-toast';
import {
  useGetUsersQuery,
  useCreateUserMutation,
  useUpdateUserMutation,
  useDeleteUserMutation,
  type User,
  type UserFormData,
} from '@/store/api/usersApiSlice';
import { useUserFilters } from './useUserFilters';

export interface UseUsersTableReturn {
  // Data
  users: User[];
  isLoading: boolean;
  isFetching: boolean;
  isError: boolean;
  error: any;
  refetch: () => void;

  // Pagination
  currentPage: number;
  pageSize: number;
  totalPages: number;
  totalUsers: number;
  setCurrentPage: (page: number) => void;
  setPageSize: (size: number) => void;

  // Mutations
  createUser: (userData: UserFormData) => Promise<any>;
  updateUser: (id: number, userData: Partial<UserFormData>) => Promise<any>;
  deleteUser: (id: number) => Promise<void>;
  isCreating: boolean;
  isUpdating: boolean;
  isDeleting: boolean;

  // CRUD Handlers
  handleCreate: (userData: UserFormData) => Promise<void>;
  handleUpdate: (id: number, userData: Partial<UserFormData>) => Promise<void>;
  handleDelete: (user: User) => Promise<void>;

  // Stats
  stats: {
    totalUsers: number;
    activeUsers: number;
    inactiveUsers: number;
    adminUsers: number;
    providerStaff: number;
  };
}

export function useUsersTable(filters = {} as ReturnType<typeof useUserFilters>): UseUsersTableReturn {
  const { toast } = useToast();
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);



  // Fetch users
  const {
    data: usersResponse,
    isLoading,
    isFetching,
    isError,
    error,
    refetch,
  } = useGetUsersQuery({
    page: currentPage,
    per_page: pageSize,
    role: filters?.roleFilter !== 'all' ? filters.roleFilter : undefined,
    status: filters?.statusFilter !== 'all' ? filters.statusFilter : undefined,
    search: filters?.searchTerm || undefined, // TODO: implement search on backend, and should correct query param be 'search' or 'q'?
  });

  // Mutations
  const [createUserMutation, { isLoading: isCreating }] = useCreateUserMutation();
  const [updateUserMutation, { isLoading: isUpdating }] = useUpdateUserMutation();
  const [deleteUserMutation, { isLoading: isDeleting }] = useDeleteUserMutation();

  // Extract data
  const users = useMemo(() => usersResponse?.data?.users ?? [], [usersResponse?.data?.users]);
  const pagination = usersResponse?.data?.pagination || {
    current_page: 1,
    per_page: 10,
    total: 0,
    last_page: 1,
  };

  // Calculate stats
  const stats = useMemo(() => {
    const totalUsers = pagination.total;
    const activeUsers = users.filter((u) => u.status === 'active').length;
    const inactiveUsers = users.filter((u) => u.status === 'inactive').length;
    const adminUsers = users.filter((u) => u.role === 'admin').length;
    const providerStaff = users.filter((u) => u.role === 'provider_staff').length;

    return {
      totalUsers,
      activeUsers,
      inactiveUsers,
      adminUsers,
      providerStaff,
    };
  }, [users, pagination.total]);

  // Create handler
  const handleCreate = async (userData: UserFormData) => {
    try {
      const result = await createUserMutation(userData).unwrap();
      toast({
        title: 'Success',
        description: result.message || 'User created successfully',
      });
    } catch (err: any) {
      toast({
        title: 'Error',
        description: err?.data?.message || 'Failed to create user',
        variant: 'destructive',
      });
      throw err;
    }
  };

  // Update handler
  const handleUpdate = async (id: number, userData: Partial<UserFormData>) => {
    try {
      const result = await updateUserMutation({ id, data: userData }).unwrap();
      toast({
        title: 'Success',
        description: result.message || 'User updated successfully',
      });
    } catch (err: any) {
      toast({
        title: 'Error',
        description: err?.data?.message || 'Failed to update user',
        variant: 'destructive',
      });
      throw err;
    }
  };

  // Delete handler
  const handleDelete = async (user: User) => {
    try {
      const result = await deleteUserMutation(user.id).unwrap();
      const userName = user?.first_name && user?.last_name
        ? `${user.first_name} ${user.last_name}`
        : user?.email || 'User';
      toast({
        title: 'Success',
        description: result.message || `User "${userName}" deleted successfully`,
      });
    } catch (err: any) {
      toast({
        title: 'Error',
        description: err?.data?.message || 'Failed to delete user',
        variant: 'destructive',
      });
      throw err;
    }
  };

  return {
    // Data
    users,
    isLoading,
    isFetching,
    isError,
    error,
    refetch,

    // Pagination
    currentPage,
    pageSize,
    totalPages: pagination.last_page,
    totalUsers: pagination.total,
    setCurrentPage,
    setPageSize,

    // Mutations
    createUser: createUserMutation,
    updateUser: (id: number, data: Partial<UserFormData>) => updateUserMutation({ id, data }),
    deleteUser: async (id: number) => { await deleteUserMutation(id).unwrap(); },
    isCreating,
    isUpdating,
    isDeleting,

    // CRUD Handlers
    handleCreate,
    handleUpdate,
    handleDelete,

    // Stats
    stats,
  };
}
