import { apiSlice, TAG_TYPES } from "./apiSlice";

// API Slice
export const testimonialApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getTestimonials: builder.query({
      query: (params) => {
        const searchParams = new URLSearchParams();

        if (params) {
          params.forEach((param: { key: string; value: string }) => {
            if (param.value) {
              searchParams.append(param.key, param.value);
            }
          });
        }

        const queryString = searchParams.toString();
        return {
          url: `/admin/testimonials`,
          method: "GET",
          params: queryString ? searchParams : undefined,
        };
      },
      providesTags: [TAG_TYPES.TESTIMONIALS]
    }),



    // Create new testimonial
    createTestimonial: builder.mutation({
      query: (formData) => ({
        url: "/admin/testimonial",
        method: "POST",
        body: formData,
      }),
      invalidatesTags: [TAG_TYPES.TESTIMONIALS],
    }),

    // Update testimonial
    updateTestimonial: builder.mutation({
      query: ({ id, formData }) => ({
        url: `/admin/testimonial/${id}`,
        method: "PUT",
        body: formData,
      }),
      invalidatesTags: [TAG_TYPES.TESTIMONIALS],
    }),

    // Delete testimonial
    deleteTestimonial: builder.mutation({
      query: (id) => ({
        url: `/admin/testimonial/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: [TAG_TYPES.TESTIMONIALS],
    }),
  }),
});

// Export hooks
export const {
  useGetTestimonialsQuery,
  useCreateTestimonialMutation,
  useUpdateTestimonialMutation,
  useDeleteTestimonialMutation,
} = testimonialApiSlice;

