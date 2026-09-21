import { apiSlice } from "./apiSlice";



export const cmsApiSlice = apiSlice.injectEndpoints({
    endpoints: (builder) => ({
        // Fetch CMS Pages
        getCmsPages: builder.query({
            query: () => ({
                url: "/get-setting-values",
                method: "GET",
            }),
            providesTags: ["CMS"],
        }),
        createUpdateCMS: builder.mutation({
            query: (formData: FormData) => ({
                url: "/setting-update",
                method: "POST",
                headers: {
                    "Accept": "multipart/form-data",
                },

                body: formData,
            }),
            invalidatesTags: ["CMS"],
        }),
    }),
});


export const { useGetCmsPagesQuery, useCreateUpdateCMSMutation } = cmsApiSlice;