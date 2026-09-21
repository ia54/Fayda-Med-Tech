import { apiSlice, TAG_TYPES } from "./apiSlice";

// API Slice
export const whyWeAreDifferentApiSlice = apiSlice.injectEndpoints({
    endpoints: (builder) => ({
        // Get all Why We Different items
        getAllWhyWeAreDifferent: builder.query({
            query: () => ({
                url: "/whywedifferent",
                method: "GET",
            }),
            providesTags: [TAG_TYPES.WHY_WE_DIFFERENT],
        }),

        // Create new Why We Different item
        createWhyWeAreDifferent: builder.mutation({
            query: (formData) => ({
                url: "/admin/whywedifferent",
                method: "POST",
                body: formData,
            }),
            invalidatesTags: [TAG_TYPES.WHY_WE_DIFFERENT],
        }),

        // Update Why We Different item
        updateWhyWeAreDifferent: builder.mutation({
            query: ({ id, formData }) => ({
                url: `/admin/whywedifferent/${id}`,
                method: "POST",
                body: formData,

            }),
            invalidatesTags: [TAG_TYPES.WHY_WE_DIFFERENT],
        }),

        // Delete Why We Different item
        deleteWhyWeAreDifferent: builder.mutation({
            query: (id) => ({
                url: `/admin/whywedifferent/${id}`,
                method: "DELETE",
            }),
            invalidatesTags: [TAG_TYPES.WHY_WE_DIFFERENT],
        }),
    }),
});

// Export hooks
export const {
    useGetAllWhyWeAreDifferentQuery,
    useCreateWhyWeAreDifferentMutation,
    useUpdateWhyWeAreDifferentMutation,
    useDeleteWhyWeAreDifferentMutation,
} = whyWeAreDifferentApiSlice;
