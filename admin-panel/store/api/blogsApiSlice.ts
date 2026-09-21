import { apiSlice, TAG_TYPES } from "./apiSlice";

// Blog Post Type
export interface Blog {
  id: number;
  title: string;
  url_slug: string;
  author: string;
  category: string;
  excerpt: string | null;
  content: string;
  tags: string[];
  status: "draft" | "published" | "schedule";
  blog_images: string[];
  blog_images_urls: string[];
  published_at: string | null;
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface BlogsResponse {
  status: boolean;
  message: string;
  data: Blog[];
}

export interface BlogResponse {
  status: boolean;
  message: string;
  data: Blog;
}

export interface DeleteBlogResponse {
  status: boolean;
  message: string;
}

// Request Types
export interface GetBlogsParams {
  status?: "draft" | "published" | "schedule";
  category?: string;
}

export interface CreateBlogData {
  title: string;
  url_slug?: string;
  author: string;
  category: string;
  excerpt?: string;
  content: string;
  tags?: string; // JSON string array
  status?: "draft" | "published" | "schedule";
  published_at?: string;
  blog_images?: File[];
}

export interface UpdateBlogData extends Partial<CreateBlogData> {
  _method?: "PUT";
  keep_existing_images?: boolean;
}

// API Slice
export const blogsApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Get all blogs with optional filters
    getBlogs: builder.query<BlogsResponse, GetBlogsParams | void>({
      query: (params) => {
        const searchParams = new URLSearchParams();
        if (params?.status) searchParams.append("status", params.status);
        if (params?.category) searchParams.append("category", params.category);

        const queryString = searchParams.toString();
        return {
          url: `/admin/blogs${queryString ? `?${queryString}` : ""}`,
          method: "GET",
        };
      },
      providesTags: (result) =>
        result
          ? [
              ...result.data.map(({ id }) => ({
                type: "Blog" as const,
                id,
              })),
              { type: "Blog" as const, id: "LIST" },
            ]
          : [{ type: "Blog" as const, id: "LIST" }],
    }),

    // Get blog by ID
    getBlogById: builder.query<BlogResponse, number>({
      query: (id) => ({
        url: `/admin/blogs/${id}`,
        method: "GET",
      }),
      providesTags: (result, error, id) => [{ type: "Blog" as const, id }],
    }),

    // Create new blog
    createBlog: builder.mutation<BlogResponse, FormData>({
      query: (formData) => ({
        url: "/admin/blogs",
        method: "POST",
        body: formData,
      }),
      invalidatesTags: [{ type: "Blog" as const, id: "LIST" }],
    }),

    // Update blog
    updateBlog: builder.mutation<BlogResponse, { id: number; formData: FormData }>({
      query: ({ id, formData }) => ({
        url: `/admin/blogs/${id}`,
        method: "POST",
        body: formData,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: "Blog" as const, id },
        { type: "Blog" as const, id: "LIST" },
      ],
    }),

    // Delete blog
    deleteBlog: builder.mutation<DeleteBlogResponse, number>({
      query: (id) => ({
        url: `/admin/blogs/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: (result, error, id) => [
        { type: "Blog" as const, id },
        { type: "Blog" as const, id: "LIST" },
      ],
    }),
  }),
});

// Export hooks
export const {
  useGetBlogsQuery,
  useGetBlogByIdQuery,
  useCreateBlogMutation,
  useUpdateBlogMutation,
  useDeleteBlogMutation,
} = blogsApiSlice;

// Helper function to create FormData from blog data
export function createBlogFormData(
  data: CreateBlogData | UpdateBlogData,
  isUpdate: boolean = false
): FormData {
  const formData = new FormData();

  // Add all fields to FormData
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && key !== "_method") {
      if (key === "blog_images" && Array.isArray(value)) {
        value.forEach((file) => {
          if (file instanceof File) {
            formData.append("blog_images[]", file);
          }
        });
      } else if (key === "tags") {
        // Tags should be sent as JSON string array
        if (Array.isArray(value)) {
          formData.append(key, JSON.stringify(value));
        } else if (typeof value === "string") {
          formData.append(key, value);
        }
      } else if (typeof value === "boolean") {
        formData.append(key, value ? "1" : "0");
      } else {
        formData.append(key, String(value));
      }
    }
  });

  // Add _method=PUT for updates (must be added AFTER other fields for Laravel)
  if (isUpdate) {
    formData.append("_method", "PUT");
  }

  return formData;
}
