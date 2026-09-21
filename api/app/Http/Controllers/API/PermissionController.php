<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Permissions",
 *     description="API Endpoints for Managing Granular System Access"
 * )
 */
class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/permissions",
     *     summary="List all available permissions",
     *     description="Retrieve a complete list of system permissions grouped by category",
     *     operationId="getPermissions",
     *     tags={"Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $permissions = Permission::all();
        $categories = Permission::select('category')->distinct()->get()->map(function($item) {
            return [
                'name' => $item->category,
                'count' => Permission::where('category', $item->category)->count()
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Permissions retrieved successfully',
            'permissions' => $permissions,
            'categories' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    /**
     * @OA\Post(
     *     path="/api/admin/permissions",
     *     summary="Create a new permission",
     *     operationId="storePermission",
     *     tags={"Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "category"},
     *             @OA\Property(property="name", type="string", example="cases.create"),
     *             @OA\Property(property="category", type="string", example="Cases"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=210, description="Permission created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $permission = Permission::create($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Permission created successfully',
            'permission' => $permission
        ], 210);
    }

    /**
     * Display the specified resource.
     */
    /**
     * @OA\Get(
     *     path="/api/admin/permissions/{id}",
     *     summary="Get permission details",
     *     operationId="getPermissionById",
     *     tags={"Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $permission = Permission::findOrFail($id);
        return response()->json([
            'status' => true,
            'message' => 'Permission details retrieved',
            'permission' => $permission
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    /**
     * @OA\Put(
     *     path="/api/admin/permissions/{id}",
     *     summary="Update permission",
     *     operationId="updatePermission",
     *     tags={"Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="category", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Permission updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string',
            'category' => 'sometimes|required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $permission->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Permission updated successfully',
            'permission' => $permission
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * @OA\Delete(
     *     path="/api/admin/permissions/{id}",
     *     summary="Delete permission",
     *     operationId="deletePermission",
     *     tags={"Permissions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Permission deleted")
     * )
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'status' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }
}
