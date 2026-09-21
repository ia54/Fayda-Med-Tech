import { baseApi } from "../api/baseApi";

// API Slice
export const faqApiSlice = baseApi.injectEndpoints({
  endpoints: (builder) => ({
    getAllFaqs: builder.query({
      query: () => ({
        url: "/faqs",
        method: "GET",
      }),
    }),

  }),
});

// Export hooks
export const {
  useGetAllFaqsQuery,
} = faqApiSlice;

