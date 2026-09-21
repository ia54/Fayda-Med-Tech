import { useState, useMemo } from "react";
import {
  useGetBlogCategoriesQuery,
  useCreateBlogCategoryMutation,
  useUpdateBlogCategoryMutation,
  useDeleteBlogCategoryMutation,
  type CreateBlogCategoryData,
  type UpdateBlogCategoryData,
} from "@/store/api/blogCategoriesApiSlice";
import { useNotifications } from "@/hooks/useNotifications";

export function useCategoriesTable() {
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  const { showSuccess, showError } = useNotifications();

  // Fetch categories with pagination
  const { data, error, isLoading, refetch, isFetching } = useGetBlogCategoriesQuery({
    page,
    per_page: pageSize,
  });

  const [createCategory, { isLoading: isCreating }] = useCreateBlogCategoryMutation();
  const [updateCategory, { isLoading: isUpdating }] = useUpdateBlogCategoryMutation();
  const [deleteCategory, { isLoading: isDeleting }] = useDeleteBlogCategoryMutation();

  const categories = useMemo(() => data?.data?.blog_categories || [], [data]);
  const pagination = useMemo(
    () =>
      data?.data?.pagination
        ? {
          page: data.data.pagination.current_page,
          pageSize: data.data.pagination.per_page,
          total: data.data.pagination.total,
        }
        : undefined,
    [data]
  );

  // Create category handler
  const handleCreate = async (categoryData: CreateBlogCategoryData) => {
    try {
      await createCategory(categoryData).unwrap();
      showSuccess("Category created successfully");
      return { success: true };
    } catch (err: any) {
      const errorMessage = err?.data?.message || "Failed to create category";
      showError(errorMessage);
      return { success: false, error: err?.data?.errors };
    }
  };

  // Update category handler
  const handleUpdate = async (id: number, categoryData: UpdateBlogCategoryData) => {
    try {
      await updateCategory({ id, data: categoryData }).unwrap();
      showSuccess("Category updated successfully");
      return { success: true };
    } catch (err: any) {
      const errorMessage = err?.data?.message || "Failed to update category";
      showError(errorMessage);
      return { success: false, error: err?.data?.errors };
    }
  };

  // Delete category handler
  const handleDelete = async (id: number) => {
    try {
      await deleteCategory(id).unwrap();
      showSuccess("Category deleted successfully");
    } catch (err: any) {
      const errorMessage =
        err?.data?.message ||
        "Failed to delete category. It may be in use by existing blogs.";
      showError(errorMessage);
    }
  };

  // Page change handler
  const handlePageChange = (newPage: number) => {
    setPage(newPage);
  };

  // Page size change handler
  const handlePageSizeChange = (newSize: number) => {
    setPageSize(newSize);
    setPage(1); // Reset to first page
  };

  // Refresh handler
  const handleRefresh = () => {
    refetch();
  };

  return {
    categories,
    pagination,
    isLoading: isLoading || isFetching,
    isCreating,
    isUpdating,
    isDeleting,
    error,
    handlePageChange,
    handlePageSizeChange,
    handleCreate,
    handleUpdate,
    handleDelete,
    handleRefresh,
  };
}
