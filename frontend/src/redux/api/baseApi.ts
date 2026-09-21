import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { tagTypesList } from "../tagTypes";

export const baseApi = createApi({
  reducerPath: "baseApi",
  baseQuery: fetchBaseQuery({
    baseUrl: `${import.meta.env.VITE_BASE_URL}`,
    // credentials: "include", // keep if you still need cookies (otherwise remove)
  }),
  endpoints: () => ({}),
  tagTypes: tagTypesList,
});
