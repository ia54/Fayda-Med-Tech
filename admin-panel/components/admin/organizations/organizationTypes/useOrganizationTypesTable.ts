import { useState, useCallback } from "react";
import { toast } from "sonner";
import {
  useGetOrganizationTypesQuery,
  useCreateOrganizationTypeMutation,
  useUpdateOrganizationTypeMutation,
  useDeleteOrganizationTypeMutation,
  type CreateOrganizationTypeData,
  type UpdateOrganizationTypeData,
} from "@/store/api/organizationTypesApiSlice";

export function useOrganizationTypesTable() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);

  // RTK Query hooks
  const {
    data: response,
    isLoading,
    isFetching,
    error,
    refetch,
  } = useGetOrganizationTypesQuery({ page, per_page: perPage });

  const [createOrganizationType, { isLoading: isCreating }] =
    useCreateOrganizationTypeMutation();

  const [updateOrganizationType, { isLoading: isUpdating }] =
    useUpdateOrganizationTypeMutation();

  const [deleteOrganizationType, { isLoading: isDeleting }] =
    useDeleteOrganizationTypeMutation();

  // Extract data
  const organizationTypes = response?.data?.organization_types || [];
  const pagination = response?.data?.pagination
    ? {
      page: response.data.pagination.current_page,
      pageSize: response.data.pagination.per_page,
      total: response.data.pagination.total,
      pageSizeOptions: [10, 20, 50, 100],
    }
    : {
      page: 1,
      pageSize: 10,
      total: 0,
      pageSizeOptions: [10, 20, 50, 100],
    };

  // Handlers
  const handlePageChange = useCallback((newPage: number) => {
    setPage(newPage);
  }, []);

  const handlePageSizeChange = useCallback((newPageSize: number) => {
    setPerPage(newPageSize);
    setPage(1); // Reset to first page when changing page size
  }, []);

  const handleRefresh = useCallback(() => {
    refetch();
  }, [refetch]);

  const handleCreate = useCallback(
    async (data: CreateOrganizationTypeData) => {
      try {
        const result = await createOrganizationType(data).unwrap();
        if (result.status) {
          toast.success(result.message || "Organization type created successfully");
          return result.data;
        } else {
          toast.error(result.message || "Failed to create organization type");
          throw new Error(result.message);
        }
      } catch (error: any) {
        console.error("Create organization type error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to create organization type";
        toast.error(message);
        throw error;
      }
    },
    [createOrganizationType]
  );

  const handleUpdate = useCallback(
    async (id: number, data: UpdateOrganizationTypeData) => {
      try {
        const result = await updateOrganizationType({ id, data }).unwrap();
        if (result.status) {
          toast.success(result.message || "Organization type updated successfully");
          return result.data;
        } else {
          toast.error(result.message || "Failed to update organization type");
          throw new Error(result.message);
        }
      } catch (error: any) {
        console.error("Update organization type error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to update organization type";
        toast.error(message);
        throw error;
      }
    },
    [updateOrganizationType]
  );

  const handleDelete = useCallback(
    async (id: number) => {
      try {
        const result = await deleteOrganizationType(id).unwrap();
        if (result.status) {
          toast.success(result.message || "Organization type deleted successfully");
          return true;
        } else {
          toast.error(result.message || "Failed to delete organization type");
          return false;
        }
      } catch (error: any) {
        console.error("Delete organization type error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to delete organization type";
        toast.error(message);
        return false;
      }
    },
    [deleteOrganizationType]
  );

  return {
    organizationTypes,
    pagination,
    isLoading: isLoading || isFetching,
    isCreating,
    isUpdating,
    isDeleting,
    error,
    handlePageChange,
    handlePageSizeChange,
    handleRefresh,
    handleCreate,
    handleUpdate,
    handleDelete,
  };
}
