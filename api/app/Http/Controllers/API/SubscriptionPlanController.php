<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionPlanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/subscription-plans",
     *     summary="Get all subscription plans",
     *     description="Retrieve a list of all subscription plans (Admin only)",
     *     operationId="getSubscriptionPlans",
     *     tags={"Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (active/inactive)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plans retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plans retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="subscription_plans", type="array", @OA\Items(ref="#/components/schemas/SubscriptionPlan")),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="last_page", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $query = SubscriptionPlan::query();

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $subscriptionPlans = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Subscription plans retrieved successfully',
                'data' => [
                    'subscription_plans' => $subscriptionPlans->items(),
                    'pagination' => [
                        'current_page' => $subscriptionPlans->currentPage(),
                        'per_page' => $subscriptionPlans->perPage(),
                        'total' => $subscriptionPlans->total(),
                        'last_page' => $subscriptionPlans->lastPage(),
                        'has_more_pages' => $subscriptionPlans->hasMorePages()
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve subscription plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/subscription-plans",
     *     summary="Create a new subscription plan",
     *     description="Create a new subscription plan (Admin only)",
     *     operationId="createSubscriptionPlan",
     *     tags={"Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"plan_name", "price", "users", "organizations"},
     *                 @OA\Property(property="plan_name", type="string", example="Premium Plan", description="Name of the subscription plan"),
     *                 @OA\Property(property="price", type="number", format="float", example=99.99, description="Price of the plan"),
     *                 @OA\Property(property="users", type="integer", example=50, description="Number of users allowed"),
     *                 @OA\Property(property="features", type="array", @OA\Items(type="string"), example={"Feature 1", "Feature 2", "Feature 3"}, description="List of features"),
     *                 @OA\Property(property="organizations", type="integer", example=5, description="Number of organizations allowed"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active", description="Status of the plan")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription plan created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plan created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/SubscriptionPlan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan_name' => 'required|string|max:255|unique:subscription_plans,plan_name',
                'price' => 'required|numeric|min:0',
                'users' => 'required|integer|min:1',
                'features' => 'nullable|array',
                'features.*' => 'string',
                'organizations' => 'required|integer|min:1',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $subscriptionPlan = SubscriptionPlan::create([
                'plan_name' => $request->plan_name,
                'price' => $request->price,
                'users' => $request->users,
                'features' => $request->features ?? [],
                'organizations' => $request->organizations,
                'status' => $request->status ?? 'active',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan created successfully',
                'data' => $subscriptionPlan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/subscription-plans/{id}",
     *     summary="Get a specific subscription plan",
     *     description="Retrieve a specific subscription plan by ID (Admin only)",
     *     operationId="getSubscriptionPlan",
     *     tags={"Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subscription plan ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plan retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plan retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/SubscriptionPlan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription plan not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription plan not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $subscriptionPlan = SubscriptionPlan::find($id);

            if (!$subscriptionPlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription plan not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan retrieved successfully',
                'data' => $subscriptionPlan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/subscription-plans/{id}",
     *     summary="Update a subscription plan",
     *     description="Update an existing subscription plan (Admin only)",
     *     operationId="updateSubscriptionPlan",
     *     tags={"Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subscription plan ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"plan_name", "price", "users", "organizations"},
     *                 @OA\Property(property="plan_name", type="string", example="Premium Plan Updated", description="Name of the subscription plan"),
     *                 @OA\Property(property="price", type="number", format="float", example=129.99, description="Price of the plan"),
     *                 @OA\Property(property="users", type="integer", example=100, description="Number of users allowed"),
     *                 @OA\Property(property="features", type="array", @OA\Items(type="string"), example={"Feature 1", "Feature 2", "Feature 3", "Feature 4"}, description="List of features"),
     *                 @OA\Property(property="organizations", type="integer", example=10, description="Number of organizations allowed"),
     *                 @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active", description="Status of the plan")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plan updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plan updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/SubscriptionPlan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription plan not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription plan not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $subscriptionPlan = SubscriptionPlan::find($id);

            if (!$subscriptionPlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription plan not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'plan_name' => 'required|string|max:255|unique:subscription_plans,plan_name,' . $id,
                'price' => 'required|numeric|min:0',
                'users' => 'required|integer|min:1',
                'features' => 'nullable|array',
                'features.*' => 'string',
                'organizations' => 'required|integer|min:1',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $subscriptionPlan->update([
                'plan_name' => $request->plan_name,
                'price' => $request->price,
                'users' => $request->users,
                'features' => $request->features ?? [],
                'organizations' => $request->organizations,
                'status' => $request->status ?? $subscriptionPlan->status,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan updated successfully',
                'data' => $subscriptionPlan->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/subscription-plans/{id}",
     *     summary="Delete a subscription plan",
     *     description="Delete an existing subscription plan (Admin only)",
     *     operationId="deleteSubscriptionPlan",
     *     tags={"Subscription Plans"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subscription plan ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plan deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plan deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription plan not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription plan not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Subscription plan is being used",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete subscription plan. It is being used by existing organizations.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $subscriptionPlan = SubscriptionPlan::find($id);

            if (!$subscriptionPlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription plan not found'
                ], 404);
            }

            // Check if subscription plan is being used by any organizations
            $organizationsCount = $subscriptionPlan->planOrganizations()->count();
            if ($organizationsCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete subscription plan. It is being used by ' . $organizationsCount . ' organization(s).'
                ], 409);
            }

            $subscriptionPlan->delete();

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete subscription plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
