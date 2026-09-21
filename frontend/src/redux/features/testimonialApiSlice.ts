import { baseApi } from "../api/baseApi";
import { apiSlice, TAG_TYPES } from "./apiSlice";

// API Slice
export const testimonialApiSlice = baseApi.injectEndpoints({
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
          url: `/testimonials`,
          method: "GET",
          params: queryString ? searchParams : undefined,
        };
      },
    }),



  }),
});

// Export hooks
export const {
  useGetTestimonialsQuery,
} = testimonialApiSlice;

