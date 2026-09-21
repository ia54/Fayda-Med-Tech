import { apiSlice, TAG_TYPES } from "./apiSlice";

// API Slice
export const faqApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getAllFaqs: builder.query({
      query: () => ({
        url: "/admin/faqs",
        method: "GET",
      }),
      providesTags: [TAG_TYPES.FAQ],
    }),

    // Create new FAQ
    createFaq: builder.mutation({
      query: (formData) => ({
        url: "/admin/faq",
        method: "POST",
        body: formData,
      }),
      invalidatesTags: [TAG_TYPES.FAQ],
    }),

    // Update FAQ
    updateFaq: builder.mutation({
      query: ({ id, formData }) => ({
        url: `/admin/faq/${id}`,
        method: "PUT",
        body: formData,
      }),
      invalidatesTags: [TAG_TYPES.FAQ],
    }),

    // Delete FAQ
    deleteFaq: builder.mutation({
      query: (id) => ({
        url: `/admin/faq/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: [TAG_TYPES.FAQ],
    }),
  }),
});

// Export hooks
export const {
  useGetAllFaqsQuery,
  useCreateFaqMutation,
  useUpdateFaqMutation,
  useDeleteFaqMutation,
} = faqApiSlice;

