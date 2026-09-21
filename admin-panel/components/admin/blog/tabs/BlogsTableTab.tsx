"use client";

import { useMemo, forwardRef, useImperativeHandle } from "react";
import { useColumnVisibility } from "@/components/global/table";
import { useBlogsTable } from "@/components/admin/blog/useBlogsTable";
import { useBlogModal } from "@/components/admin/blog/useBlogModal";
import { useBlogFilters } from "@/components/admin/blog/useBlogFilters";
import { getBlogColumns } from "@/components/admin/blog/blogsTableConfig";
import {
  getEmptyState,
  getLoadingState,
} from "@/components/admin/blog/blogPageConfig";
import { BlogModal } from "@/components/admin/blog/BlogModal";
import { DataTable } from "@/components/global/table";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Label } from "@/components/ui/label";
import { useGetBlogCategoriesQuery } from "@/store/api/blogCategoriesApiSlice";
import { LoadingSpinner } from "@/components/loading-spinner";

export const BlogsTableTab = forwardRef<{ openModal: () => void }>(
  function BlogsTableTab(props, ref) {
    // Data & API handlers
    const {
      blogs,
      isLoading,
      isCreating,
      isUpdating,
      error,
      statusFilter,
      setStatusFilter,
      categoryFilter,
      setCategoryFilter,
      handleCreate,
      handleUpdate,
      handleDelete,
      handleRefresh,
    } = useBlogsTable();

    // Modal management
    const {
      modalState,
      apiErrors,
      errorMessage,
      openModal,
      closeModal,
      handleSubmit,
    } = useBlogModal(handleCreate, handleUpdate);

    // Expose openModal to parent via ref
    useImperativeHandle(ref, () => ({
      openModal: () => openModal("create"),
    }));

    // Filters
    const {
      searchTerm,
      setSearchTerm,
      dateRange,
      handleDateRangeChange,
      filteredBlogs,
    } = useBlogFilters(blogs);

    // Table columns
    const columns = useMemo(
      () =>
        getBlogColumns({
          onView: (blog) => openModal("view", blog),
          onEdit: (blog) => openModal("edit", blog),
          onDelete: handleDelete,
        }),
      [openModal, handleDelete]
    );

    const { columnVisibility, setColumnVisibility } =
      useColumnVisibility(columns);

    // Page configurations
    const emptyState = useMemo(
      () => getEmptyState(() => openModal("create")),
      [openModal]
    );
    const loadingState = useMemo(() => getLoadingState(isLoading), [isLoading]);

    // Get unique categories from blogs for filter
    const { data: availableCategoriesRes, isLoading: isLoadingBlogCategories } =
      useGetBlogCategoriesQuery({
        per_page: 500,
      });
    const availableCategories = availableCategoriesRes?.data?.blog_categories;

    return (
      <>
        <BlogModal
          open={modalState.open}
          onOpenChange={closeModal}
          mode={modalState.mode}
          blog={modalState.blog}
          onSubmit={handleSubmit}
          isLoading={modalState.mode === "create" ? isCreating : isUpdating}
          apiErrors={apiErrors}
          errorMessage={errorMessage}
        />

        <DataTable
          // Title & Description (inside table card)
          title="All Blog Posts"
          description="View and manage all blog posts"
          // Filters (inside table card)
          // searchValue={searchTerm}
          // onSearchChange={setSearchTerm}
          searchPlaceholder="Search by title, author, category, or tags..."
          // showDateRange={true}
          // dateRangeValue={dateRange}
          // onDateRangeChange={handleDateRangeChange}
          // dateRangePlaceholder="Filter by creation date..."
          // Custom filters section
          customFilters={
            <div className="flex flex-wrap gap-4">
              <div className="flex items-center gap-2 min-w-[200px]">
                <Label className="text-sm whitespace-nowrap">Status:</Label>
                <Select
                  value={statusFilter || "all"}
                  onValueChange={(value) =>
                    setStatusFilter(
                      value === "all" ? undefined : (value as any)
                    )
                  }
                >
                  <SelectTrigger className="h-9">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Status</SelectItem>
                    <SelectItem value="published">Published</SelectItem>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="schedule">Scheduled</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {availableCategories && availableCategories.length > 0 && (
                <div className="flex items-center gap-2 min-w-[200px]">
                  <Label className="text-sm whitespace-nowrap">Category:</Label>
                  <Select
                    value={categoryFilter || "all"}
                    onValueChange={(value) =>
                      setCategoryFilter(value === "all" ? undefined : value)
                    }
                    disabled={isLoadingBlogCategories}
                  >
                    <SelectTrigger className="h-9">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">
                        All Categories{" "}
                        {isLoadingBlogCategories && <LoadingSpinner />}
                      </SelectItem>
                      {availableCategories.map((category) => (
                        <SelectItem key={category.id} value={category.category}>
                          {category.category}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
            </div>
          }
          // Table Data
          data={filteredBlogs}
          columns={columns}
          columnVisibility={columnVisibility}
          onColumnVisibilityChange={setColumnVisibility}
          // Pagination (disabled since API doesn't support it yet)
          enablePagination={false}
          // States
          loading={loadingState}
          emptyState={emptyState}
          errorState={{ error: error as Error, onRetry: handleRefresh }}
          // Row Config
          rowKey="id"
          rowClassName={(blog) =>
            blog.status === "published" ? "bg-accent/5" : ""
          }
          // Actions
          onRefresh={handleRefresh}
          showRefreshButton={true}
          showColumnToggle={true}
          ariaLabel="Blog posts table"
        />
      </>
    );
  }
);
