import { useState, useMemo } from "react";
import {
  useGetBlogsQuery,
  useCreateBlogMutation,
  useUpdateBlogMutation,
  useDeleteBlogMutation,
  type CreateBlogData,
  type UpdateBlogData,
  createBlogFormData,
} from "@/store/api/blogsApiSlice";
import { useNotifications } from "@/hooks/useNotifications";

export function useBlogsTable() {
  const [statusFilter, setStatusFilter] = useState<"draft" | "published" | "schedule" | undefined>(undefined);
  const [categoryFilter, setCategoryFilter] = useState<string | undefined>(undefined);

  const { showSuccess, showError } = useNotifications();

  // Fetch blogs with filters
  const { data, error, isLoading, refetch, isFetching } = useGetBlogsQuery(
    statusFilter || categoryFilter ? { status: statusFilter, category: categoryFilter } : undefined
  );

  const [createBlog, { isLoading: isCreating }] = useCreateBlogMutation();
  const [updateBlog, { isLoading: isUpdating }] = useUpdateBlogMutation();
  const [deleteBlog, { isLoading: isDeleting }] = useDeleteBlogMutation();

  const blogs = useMemo(() => data?.data || [], [data]);

  // Calculate stats
  const stats = useMemo(() => {
    const totalBlogs = blogs.length;
    const publishedBlogs = blogs.filter((b) => b.status === "published").length;
    const draftBlogs = blogs.filter((b) => b.status === "draft").length;
    const scheduledBlogs = blogs.filter((b) => b.status === "schedule").length;

    return { totalBlogs, publishedBlogs, draftBlogs, scheduledBlogs };
  }, [blogs]);

  // Create blog handler
  const handleCreate = async (blogData: CreateBlogData) => {
    try {
      const formData = createBlogFormData(blogData, false);
      await createBlog(formData).unwrap();
      showSuccess("Blog post created successfully");
      return { success: true };
    } catch (err: any) {
      const errorMessage = err?.data?.message || "Failed to create blog post";
      showError(errorMessage);
      return { success: false, error: err?.data?.errors };
    }
  };

  // Update blog handler
  const handleUpdate = async (id: number, blogData: UpdateBlogData) => {
    try {
      const formData = createBlogFormData(blogData, true);
      await updateBlog({ id, formData }).unwrap();
      showSuccess("Blog post updated successfully");
      return { success: true };
    } catch (err: any) {
      const errorMessage = err?.data?.message || "Failed to update blog post";
      showError(errorMessage);
      return { success: false, error: err?.data?.errors };
    }
  };

  // Delete blog handler
  const handleDelete = async (id: number) => {
    try {
      await deleteBlog(id).unwrap();
      showSuccess("Blog post deleted successfully");
    } catch (err: any) {
      const errorMessage = err?.data?.message || "Failed to delete blog post";
      showError(errorMessage);
    }
  };

  // Refresh handler
  const handleRefresh = () => {
    refetch();
  };

  // Export handler (placeholder)
  const handleExport = () => {
    showSuccess("Export functionality coming soon");
  };

  return {
    blogs,
    stats,
    isLoading: isLoading || isFetching,
    isCreating,
    isUpdating,
    isDeleting,
    error,
    statusFilter,
    setStatusFilter,
    categoryFilter,
    setCategoryFilter,
    handleCreate,
    handleUpdate,
    handleDelete,
    handleRefresh,
    handleExport,
  };
}
