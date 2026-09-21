import { createApi, fetchBaseQuery } from "@reduxjs/toolkit/query/react";
import { logOut, setUser } from "../features/auth/authSlice";
import { tagTypesList } from "../tagTypes";

// Add here all endpoints that don't require auth
const skipAuthEndpoints = ["getAllServices"];

const baseQuery = fetchBaseQuery({
  baseUrl: `${import.meta.env.VITE_BASE_URL}/api`,
  credentials: "include",
  prepareHeaders: (headers, { getState, endpoint }) => {
    if (skipAuthEndpoints.includes(endpoint)) {
      return headers; // skip Authorization
    }

    const token = getState().auth.token;
    if (token) {
      headers.set("Authorization", `Bearer ${token}`);
    }

    return headers;
  },
});

const BaseQueryWithRefreshToken = async (args, api, extraOptions) => {
  let result = await baseQuery(args, api, extraOptions);

  // Only try refresh if user is still logged in
  const isLoggedIn = !!api.getState().auth.token;

  if (result?.error?.status === 401 && isLoggedIn) {
    console.log("Attempting token refresh...");

    try {
      const res = await fetch(`${import.meta.env.VITE_BASE_URL}/api/refresh`, {
        method: "POST",
        credentials: "include",
      });

      const data = await res.json();

      if (data?.access_token) {
        const user = api.getState().auth.user;

        api.dispatch(
          setUser({
            user,
            token: data?.access_token,
          })
        );

        result = await baseQuery(args, api, extraOptions);
      } else {
        api.dispatch(logOut());
      }
    } catch (e) {
      console.error("Refresh token failed", e);
      api.dispatch(logOut());
    }
  }

  return result;
};

export const baseApi = createApi({
  reducerPath: "baseApi",
  baseQuery: BaseQueryWithRefreshToken,
  endpoints: () => ({}),
  tagTypes: tagTypesList,
});
