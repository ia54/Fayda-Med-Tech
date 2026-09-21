import { useState, useCallback } from "react";
import { toast } from "sonner";
import {
  useGetSubscriptionPlansQuery,
  useCreateSubscriptionPlanMutation,
  useUpdateSubscriptionPlanMutation,
  useDeleteSubscriptionPlanMutation,
  type CreateSubscriptionPlanData,
  type UpdateSubscriptionPlanData,
} from "@/store/api/subscriptionPlansApiSlice";

export function useSubscriptionPlansTable() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [statusFilter, setStatusFilter] = useState<"active" | "inactive" | undefined>(undefined);

  // RTK Query hooks
  const {
    data: response,
    isLoading,
    isFetching,
    error,
    refetch,
  } = useGetSubscriptionPlansQuery({
    page,
    per_page: perPage,
    status: statusFilter,
  });

  const [createSubscriptionPlan, { isLoading: isCreating }] =
    useCreateSubscriptionPlanMutation();

  const [updateSubscriptionPlan, { isLoading: isUpdating }] =
    useUpdateSubscriptionPlanMutation();

  const [deleteSubscriptionPlan, { isLoading: isDeleting }] =
    useDeleteSubscriptionPlanMutation();

  // Extract data
  const subscriptionPlans = response?.data?.subscription_plans || [];
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
    setPage(1);
  }, []);

  const handleStatusFilterChange = useCallback((status: "active" | "inactive" | undefined) => {
    setStatusFilter(status);
    setPage(1);
  }, []);

  const handleRefresh = useCallback(() => {
    refetch();
  }, [refetch]);

  const handleCreate = useCallback(
    async (data: CreateSubscriptionPlanData) => {
      try {
        const result = await createSubscriptionPlan(data).unwrap();
        if (result.status) {
          toast.success(result.message || "Subscription plan created successfully");
          return result.data;
        } else {
          toast.error(result.message || "Failed to create subscription plan");
          throw new Error(result.message);
        }
      } catch (error: any) {
        console.error("Create subscription plan error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to create subscription plan";
        toast.error(message);
        throw error;
      }
    },
    [createSubscriptionPlan]
  );

  const handleUpdate = useCallback(
    async (id: number, data: UpdateSubscriptionPlanData) => {
      try {
        const result = await updateSubscriptionPlan({ id, data }).unwrap();
        if (result.status) {
          toast.success(result.message || "Subscription plan updated successfully");
          return result.data;
        } else {
          toast.error(result.message || "Failed to update subscription plan");
          throw new Error(result.message);
        }
      } catch (error: any) {
        console.error("Update subscription plan error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to update subscription plan";
        toast.error(message);
        throw error;
      }
    },
    [updateSubscriptionPlan]
  );

  const handleDelete = useCallback(
    async (id: number) => {
      try {
        const result = await deleteSubscriptionPlan(id).unwrap();
        if (result.status) {
          toast.success(result.message || "Subscription plan deleted successfully");
          return true;
        } else {
          toast.error(result.message || "Failed to delete subscription plan");
          return false;
        }
      } catch (error: any) {
        console.error("Delete subscription plan error:", error);
        const message =
          error?.data?.message ||
          error?.message ||
          "Failed to delete subscription plan";
        toast.error(message);
        return false;
      }
    },
    [deleteSubscriptionPlan]
  );

  return {
    subscriptionPlans,
    pagination,
    statusFilter,
    isLoading: isLoading || isFetching,
    isCreating,
    isUpdating,
    isDeleting,
    error,
    handlePageChange,
    handlePageSizeChange,
    handleStatusFilterChange,
    handleRefresh,
    handleCreate,
    handleUpdate,
    handleDelete,
  };
}
