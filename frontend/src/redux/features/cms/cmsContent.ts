import { baseApi } from "../../api/baseApi";

const cmsContentApi = baseApi.injectEndpoints({
  endpoints: (build) => ({
    getCMSContent: build.query({
      query: () => {
        return {
          url: "/get-setting-values",
          method: "GET",
        };
      },
    }),
  }),
});

export const { useGetCMSContentQuery } = cmsContentApi;
