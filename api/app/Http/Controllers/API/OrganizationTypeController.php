<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrganizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/organization-types",
     *     summary="Get all organization types",
     *     description="Retrieve a list of all organization types (Admin only)",
     *     operationId="getOrganizationTypes",
     *     tags={"Organization Types"},
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
     *     @OA\Response(
     *         response=200,
     *         description="Organization types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization types retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="organization_types", type="array", @OA\Items(ref="#/components/schemas/OrganizationType")),
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
            $organizationTypes = OrganizationType::paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Organization types retrieved successfully',
                'data' => [
                    'organization_types' => $organizationTypes->items(),
                    'pagination' => [
                        'current_page' => $organizationTypes->currentPage(),
                        'per_page' => $organizationTypes->perPage(),
                        'total' => $organizationTypes->total(),
                        'last_page' => $organizationTypes->lastPage(),
                        'has_more_pages' => $organizationTypes->hasMorePages()
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve organization types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/organization-types",
     *     summary="Create a new organization type",
     *     description="Create a new organization type (Admin only)",
     *     operationId="createOrganizationType",
     *     tags={"Organization Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"type_name"},
     *                 @OA\Property(property="type_name", type="string", example="Healthcare", description="Name of the organization type"),
     *                 @OA\Property(property="description", type="string", example="Organizations in the healthcare sector", description="Description of the organization type")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization type created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization type created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrganizationType")
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
                'type_name' => 'required|string|max:255|unique:organization_types,type_name',
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $organizationType = OrganizationType::create([
                'type_name' => $request->type_name,
                'description' => $request->description,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Organization type created successfully',
                'data' => $organizationType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create organization type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/organization-types/{id}",
     *     summary="Get a specific organization type",
     *     description="Retrieve a specific organization type by ID (Admin only)",
     *     operationId="getOrganizationType",
     *     tags={"Organization Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization type retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization type retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrganizationType")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization type not found")
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
            $organizationType = OrganizationType::find($id);

            if (!$organizationType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization type not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Organization type retrieved successfully',
                'data' => $organizationType
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve organization type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/organization-types/{id}",
     *     summary="Update an organization type",
     *     description="Update an existing organization type (Admin only)",
     *     operationId="updateOrganizationType",
     *     tags={"Organization Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"type_name"},
     *                 @OA\Property(property="type_name", type="string", example="Healthcare", description="Name of the organization type"),
     *                 @OA\Property(property="description", type="string", example="Organizations in the healthcare sector", description="Description of the organization type")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization type updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization type updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrganizationType")
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
     *         description="Organization type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization type not found")
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
            $organizationType = OrganizationType::find($id);

            if (!$organizationType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization type not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'type_name' => 'required|string|max:255|unique:organization_types,type_name,' . $id,
                'description' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $organizationType->update([
                'type_name' => $request->type_name,
                'description' => $request->description,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Organization type updated successfully',
                'data' => $organizationType->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update organization type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/organization-types/{id}",
     *     summary="Delete an organization type",
     *     description="Delete an existing organization type (Admin only)",
     *     operationId="deleteOrganizationType",
     *     tags={"Organization Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization type ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization type deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization type deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization type not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Organization type is being used",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete organization type. It is being used by existing organizations.")
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
            $organizationType = OrganizationType::find($id);

            if (!$organizationType) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization type not found'
                ], 404);
            }

            // Check if organization type is being used by any organizations
            $organizationsCount = $organizationType->organizations()->count();
            if ($organizationsCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete organization type. It is being used by ' . $organizationsCount . ' organization(s).'
                ], 409);
            }

            $organizationType->delete();

            return response()->json([
                'status' => true,
                'message' => 'Organization type deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete organization type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}