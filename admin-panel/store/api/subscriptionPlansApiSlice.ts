import { apiSlice, TAG_TYPES } from "./apiSlice";

// Subscription Plan Interface
export interface SubscriptionPlan {
  id: number;
  plan_name: string;
  price: number;
  users: number;
  features: string[];
  organizations: number;
  status: "active" | "inactive";
  created_at: string;
  updated_at: string;
}

// API Response Types
export interface SubscriptionPlansResponse {
  status: boolean;
  message: string;
  data: {
    subscription_plans: SubscriptionPlan[];
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      last_page: number;
    };
  };
}

export interface SubscriptionPlanResponse {
  status: boolean;
  message: string;
  data: SubscriptionPlan;
}

export interface DeleteSubscriptionPlanResponse {
  status: boolean;
  message: string;
}

// Request Types
export interface GetSubscriptionPlansParams {
  page?: number;
  per_page?: number;
  status?: "active" | "inactive";
}

export interface CreateSubscriptionPlanData {
  plan_name: string;
  price: number;
  users: number;
  features: string[];
  organizations: number;
  status: "active" | "inactive";
}

export interface UpdateSubscriptionPlanData {
  plan_name?: string;
  price?: number;
  users?: number;
  features?: string[];
  organizations?: number;
  status?: "active" | "inactive";
}

// API Slice
export const subscriptionPlansApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Get all subscription plans with pagination and filtering
    getSubscriptionPlans: builder.query<
      SubscriptionPlansResponse,
      GetSubscriptionPlansParams | void
    >({
      query: (params) => {
        const queryParams: Record<string, any> = {
          page: params?.page || 1,
          per_page: params?.per_page || 10,
        };

        if (params?.status) {
          queryParams.status = params.status;
        }

        return {
          url: `/subscription-plans`,
          method: "GET",
          params: queryParams,
        };
      },
      providesTags: (result) =>
        result
          ? [
              ...result.data.subscription_plans.map(({ id }) => ({
                type: TAG_TYPES.SUBSCRIPTION_PLAN,
                id,
              })),
              { type: TAG_TYPES.SUBSCRIPTION_PLAN, id: "LIST" },
            ]
          : [{ type: TAG_TYPES.SUBSCRIPTION_PLAN, id: "LIST" }],
    }),

    // Get subscription plan by ID
    getSubscriptionPlanById: builder.query<SubscriptionPlanResponse, number>({
      query: (id) => ({
        url: `/subscription-plans/${id}`,
        method: "GET",
      }),
      providesTags: (result, error, id) => [{ type: TAG_TYPES.SUBSCRIPTION_PLAN, id }],
    }),

    // Create new subscription plan
    createSubscriptionPlan: builder.mutation<
      SubscriptionPlanResponse,
      CreateSubscriptionPlanData
    >({
      query: (data) => ({
        url: "/subscription-plans",
        method: "POST",
        body: data,
      }),
      invalidatesTags: [{ type: TAG_TYPES.SUBSCRIPTION_PLAN, id: "LIST" }],
    }),

    // Update subscription plan
    updateSubscriptionPlan: builder.mutation<
      SubscriptionPlanResponse,
      { id: number; data: UpdateSubscriptionPlanData }
    >({
      query: ({ id, data }) => ({
        url: `/subscription-plans/${id}`,
        method: "PUT",
        body: data,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: TAG_TYPES.SUBSCRIPTION_PLAN, id },
        { type: TAG_TYPES.SUBSCRIPTION_PLAN, id: "LIST" },
      ],
    }),

    // Delete subscription plan
    deleteSubscriptionPlan: builder.mutation<
      DeleteSubscriptionPlanResponse,
      number
    >({
      query: (id) => ({
        url: `/subscription-plans/${id}`,
        method: "DELETE",
      }),
      invalidatesTags: (result, error, id) => [
        { type: TAG_TYPES.SUBSCRIPTION_PLAN, id },
        { type: TAG_TYPES.SUBSCRIPTION_PLAN, id: "LIST" },
      ],
    }),
  }),
});

// Export hooks
export const {
  useGetSubscriptionPlansQuery,
  useGetSubscriptionPlanByIdQuery,
  useCreateSubscriptionPlanMutation,
  useUpdateSubscriptionPlanMutation,
  useDeleteSubscriptionPlanMutation,
} = subscriptionPlansApiSlice;
