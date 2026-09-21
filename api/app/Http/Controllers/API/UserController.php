<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewUserCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="Get all users",
     *     description="Retrieve a list of all users (Admin only)",
     *     operationId="getAllUsers",
     *     tags={"Users"},
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
     *         name="role",
     *         in="query",
     *         description="Filter by role",
     *         required=false,
     *         @OA\Schema(type="string", enum={"provider_staff", "billing_team", "law_firm_staff", "supervisor", "admin"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="users", type="array", @OA\Items(ref="#/components/schemas/User")),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="last_page", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $query = User::with('organizationRelation:id,org_name');
            $authUser = $request->user();

            // Enforce Tenant Isolation
            if ($authUser->role !== 'admin') {
                $query->where('organization_id', $authUser->organization_id);
            }

            // Filter by role if provided
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }
            if ($request->name) {
                $query->where(
                    DB::raw("CONCAT(first_name, ' ', last_name)"),
                    'like',
                    '%' . $request->name . '%'
                );
            }
            if ($request->status && $request->status !== 'all'){
                $query->where('status',$request->status);
            }

            $users = $query->paginate($perPage);

            // Hide sensitive information
            $users->getCollection()->transform(function ($user) {
                unset($user->remember_token);
                return $user;
            });

            return response()->json([
                'status' => true,
                'message' => 'Users retrieved successfully',
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'last_page' => $users->lastPage(),
                        'has_more_pages' => $users->hasMorePages()
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/users",
     *     summary="Create a new user",
     *     description="Create a new user (Admin only). Can optionally send welcome email with credentials.",
     *     operationId="createNewUser",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"first_name", "last_name", "email", "password", "role"},
     *                 @OA\Property(property="first_name", type="string", example="John", description="User's first name"),
     *                 @OA\Property(property="last_name", type="string", example="Doe", description="User's last name"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com", description="User's email address"),
                @OA\Property(property="password", type="string", format="password", example="SecurePass123!", description="User's password"),
                @OA\Property(property="organization_id", type="integer", example=1, description="ID of the organization"),
                @OA\Property(property="role", type="string", enum={"provider_staff", "billing_team", "law_firm_staff", "supervisor", "admin"}, example="provider_staff", description="User's role"),
                @OA\Property(property="status", type="string", enum={"active", "pending", "inactive"}, example="active", description="User's status"),
                @OA\Property(property="send_email", type="boolean", example=true, description="Send welcome email to user")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="email_sent", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function store(Request $request)
    {
        try {
            $authUser = $request->user();
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'organization_id' => $authUser->role === 'admin' ? 'nullable|exists:organizations,id' : 'nullable',
                'role' => 'required|in:admin,firm_admin,attorney,medical_biller,provider_staff,client',
                'status' => 'nullable|in:active,pending,inactive',
                'send_email' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Security check: Only Super Admin can create another Super Admin
            if ($request->role === 'admin' && $authUser->role !== 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to create a Super Admin account.'
                ], 403);
            }

            // Force organization_id for non-super-admins
            $organizationId = $request->organization_id;
            if ($authUser->role !== 'admin') {
                $organizationId = $authUser->organization_id;
            }

            // Store plain password temporarily for email
            $plainPassword = $request->password;

            // Get organization name
            $organizationName = null;
            if ($organizationId) {
                $org = \App\Models\Organization::find($organizationId);
                $organizationName = $org ? $org->org_name : null;
            }

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => $plainPassword, // Will be hashed by model
                'organization_id' => $organizationId,
                'organization' => $organizationName,
                'role' => $request->role,
                'status' => $request->status ?? 'active',
            ]);

            $emailSent = false;

            // Send email if checkbox is checked
            if ($request->send_email === true || $request->send_email === 'true' || $request->send_email === 1) {
                try {
                    $userName = $user->first_name . ' ' . $user->last_name;
                    $user->notify(new NewUserCreated($plainPassword, $userName));
                    $emailSent = true;
                } catch (\Exception $e) {
                    \Log::error('Failed to send user creation email: ' . $e->getMessage());
                }
            }

            unset($user->remember_token);

            return response()->json([
                'status' => true,
                'message' => 'User created successfully' . ($emailSent ? ' and welcome email sent' : ''),
                'data' => [
                    'user' => $user,
                    'email_sent' => $emailSent
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}",
     *     summary="Get a specific user",
     *     description="Retrieve a specific user by ID (Admin only)",
     *     operationId="getUserById",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function show(Request $request, $id)
    {
        try {
            $authUser = $request->user() ?? auth()->user(); // Fallback if $request is not available
            $user = User::with('organizationRelation:id,org_name')->find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Tenant Isolation
            if ($authUser->role !== 'admin' && $user->organization_id !== $authUser->organization_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to user in different organization.'
                ], 403);
            }

            unset($user->remember_token);

            return response()->json([
                'status' => true,
                'message' => 'User retrieved successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}",
     *     summary="Update a user",
     *     description="Update an existing user (Admin only)",
     *     operationId="updateUserById",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"first_name", "last_name", "email", "role"},
     *                 @OA\Property(property="first_name", type="string", example="John", description="User's first name"),
     *                 @OA\Property(property="last_name", type="string", example="Doe", description="User's last name"),
     *                 @OA\Property(property="email", type="string", format="email", example="john.doe@example.com", description="User's email address"),
                @OA\Property(property="password", type="string", format="password", example="NewSecurePass123!", description="New password (optional)"),
                @OA\Property(property="organization_id", type="integer", example=1, description="ID of the organization"),
                @OA\Property(property="role", type="string", enum={"provider_staff", "billing_team", "law_firm_staff", "supervisor", "admin"}, example="supervisor", description="User's role"),
                @OA\Property(property="status", type="string", enum={"active", "pending", "inactive"}, example="active", description="User's status")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $authUser = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Tenant Isolation
            if ($authUser->role !== 'admin' && $user->organization_id !== $authUser->organization_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to user in different organization.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $id,
                'password' => 'nullable|string|min:8',
                'organization_id' => $authUser->role === 'admin' ? 'nullable|exists:organizations,id' : 'nullable',
                'role' => 'required|in:admin,firm_admin,attorney,medical_biller,provider_staff,client',
                'status' => 'nullable|in:active,pending,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Security check: Only Super Admin can assign/keep Super Admin role
            if (($request->role === 'admin' || $user->role === 'admin') && $authUser->role !== 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to manage Super Admin accounts.'
                ], 403);
            }

            // Get organization name
            $organizationId = $user->organization_id;
            $organizationName = $user->organization;
            
            if ($authUser->role === 'admin' && $request->has('organization_id')) {
                $organizationId = $request->organization_id;
                if ($organizationId) {
                    $org = \App\Models\Organization::find($organizationId);
                    $organizationName = $org ? $org->org_name : null;
                } else {
                    $organizationName = null;
                }
            }

            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'organization_id' => $organizationId,
                'organization' => $organizationName,
                'role' => $request->role,
                'status' => $request->status ?? $user->status,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = $request->password;
            }

            $user->update($updateData);

            unset($user->remember_token);

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully',
                'data' => $user->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/users/{id}",
     *     summary="Delete a user",
     *     description="Delete an existing user (Admin only)",
     *     operationId="deleteUserById",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=409, description="Cannot delete own account"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function destroy(Request $request, $id)
    {
        try {
            $authUser = $request->user();
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Tenant Isolation
            if ($authUser->role !== 'admin' && $user->organization_id !== $authUser->organization_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access to user in different organization.'
                ], 403);
            }
            
            // Security check: Only Super Admin can delete another Super Admin
            if ($user->role === 'admin' && $authUser->role !== 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to delete a Super Admin account.'
                ], 403);
            }

            // Prevent deleting own account
            if ($authUser->id == $id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot delete your own account'
                ], 409);
            }

            $user->delete();

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
