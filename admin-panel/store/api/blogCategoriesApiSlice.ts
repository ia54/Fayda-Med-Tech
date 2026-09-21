import { apiSlice, TAG_TYPES } from "./apiSlice";

// Blog Category Type
export interface BlogCategory {
  id: number;
  category: string;
  description: string | null;
  post: number;
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface BlogCategoriesResponse {
  status: boolean;
  message: string;
  data: {
    blog_categories: BlogCategory[];
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface BlogCategoryResponse {
  status: boolean;
  message: string;
  data: BlogCategory;
}

export interface DeleteBlogCategoryResponse {
  status: boolean;
  message: string;
}

// Request Types
export interface GetBlogCategoriesParams {
  page?: number;
  per_page?: number;
}

export interface CreateBlogCategoryData {
  category: string;
  description?: string;
  post?: number;
}

export interface UpdateBlogCategoryData extends Partial<CreateBlogCategoryData> {}

// API Slice
export const blogCategoriesApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Get all blog categories with pagination
    getBlogCategories: builder.query<BlogCategoriesResponse, GetBlogCategoriesParams | void>({
      query: (params) => {
        const page = params?.page || 1;
        const per_page = params?.per_page || 10;
        return {
          url: `/admin/blog-categories?page=${page}&per_page=${per_page}`,
          method: "GET",
        };
      },
      providesTags: (result) =>
        result
          ? [
              ...result.data.blog_categories.map(({ id }) => ({
                type: "BlogCategory" as const,
                id,
              })),
              { type: "BlogCategory" as const, id: "LIST" },
            ]
          : [{ type: "BlogCategory" as const, id: "LIST" }],
    }),

    // Get blog category by ID
    getBlogCategoryById: builder.query<BlogCategoryResponse, number>({
      query: (id) => ({
        url: `/admin/blog-categories/${id}`,
        method: "GET",
      }),
      providesTags: (result, error, id) => [{ type: "BlogCategory" as const, id }],
    }),

    // Create new blog category
    createBlogCategory: builder.mutation<BlogCategoryResponse, CreateBlogCategoryData>({
      query: (data) => ({
        url: "/admin/blog-categories",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      }),
      invalidatesTags: [{ type: "BlogCategory" as const, id: "LIST" }],
    }),

    // Update blog category
    updateBlogCategory: builder.mutation<
      BlogCategoryResponse,
      { id: number; data: UpdateBlogCategoryData }
    >({
      query: ({ id, data }) => ({
        url: `/admin/blog-categories/${id}`,
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: "BlogCategory" as const, id },
        { type: "BlogCategory" as const, id: "LIST" },
      ],
    }),

    // Delete blog category
    deleteBlogCategory: builder.mutation<DeleteBlogCategoryResponse, number>({
      query: (id) => ({
        url: `/admin/blog-categories/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: (result, error, id) => [
        { type: "BlogCategory" as const, id },
        { type: "BlogCategory" as const, id: "LIST" },
      ],
    }),
  }),
});

// Export hooks
export const {
  useGetBlogCategoriesQuery,
  useGetBlogCategoryByIdQuery,
  useCreateBlogCategoryMutation,
  useUpdateBlogCategoryMutation,
  useDeleteBlogCategoryMutation,
} = blogCategoriesApiSlice;
