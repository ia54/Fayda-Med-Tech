import { baseApi } from "../api/baseApi";

// API Slice
export const whyWeAreDifferentApiSlice = baseApi.injectEndpoints({
    endpoints: (builder) => ({
        // Get all Why We Different items
        getAllWhyWeAreDifferent: builder.query({
            query: () => ({
                url: "/whywedifferent",
                method: "GET",
            }),
        }),
    }),
});

// Export hooks
export const {
    useGetAllWhyWeAreDifferentQuery,
} = whyWeAreDifferentApiSlice;
