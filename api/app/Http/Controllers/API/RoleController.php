<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints for RBAC Role Management"
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/roles",
     *     summary="List all user roles",
     *     operationId="getRoles",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $roles = Role::latest()->get();
        
        // Add user count for each role
        $roles->map(function($role) {
            $role->userCount = User::where('role', $role->slug)->count();
            return $role;
        });

        return response()->json([
            'status' => true,
            'message' => 'Roles retrieved successfully',
            'roles' => $roles
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/roles",
     *     summary="Create a custom role",
     *     operationId="storeRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Compliance Officer"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=210, description="Role created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'color' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $role = Role::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'permissions' => $request->permissions ?? [],
            'color' => $request->color ?? 'default'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully',
            'role' => $role
        ], 210);
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/api/admin/roles/{id}",
     *     summary="Get role details",
     *     operationId="getRoleById",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $role = Role::findOrFail($id);
        $role->userCount = User::where('role', $role->slug)->count();

        return response()->json([
            'status' => true,
            'message' => 'Role details retrieved',
            'role' => $role
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/api/admin/roles/{id}",
     *     summary="Update role",
     *     operationId="updateRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'color' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $role->update($request->only(['name', 'description', 'permissions', 'color']));
        
        if ($request->has('name')) {
            $role->slug = Str::slug($request->name);
            $role->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully',
            'role' => $role
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/api/admin/roles/{id}",
     *     summary="Delete role",
     *     operationId="deleteRole",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Role deleted")
     * )
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        
        // Prevent deleting core system roles if needed
        $coreRoles = ['admin', 'firm_admin', 'attorney', 'medical_biller', 'provider_staff', 'client'];
        if (in_array($role->slug, $coreRoles)) {
            return response()->json([
                'status' => false, 
                'message' => 'Cannot delete core system roles'
            ], 403);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
