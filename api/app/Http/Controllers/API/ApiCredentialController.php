<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="API Credentials",
 *     description="API Endpoints for Managing Integration Keys"
 * )
 */
class ApiCredentialController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/api-credentials",
     *     summary="List all API credentials",
     *     description="Retrieve a list of configured API providers (Google Vision, DocuSign, etc.)",
     *     operationId="listApiCredentials",
     *     tags={"API Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $user = auth()->user();

        $query = ApiCredential::query();
        if ($user->role !== 'admin') {
            // Tenant administrators must never receive platform credentials.
            $query->where('organization_id', $user->organization_id);
        }
        $credentials = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'API Credentials retrieved successfully',
            'data' => $credentials
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/api-credentials",
     *     summary="Create/Update API credential",
     *     description="Register a new API key for a provider",
     *     operationId="storeApiCredential",
     *     tags={"API Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"provider", "name", "key"},
     *             @OA\Property(property="provider", type="string", example="google_vision"),
     *             @OA\Property(property="name", type="string", example="API_KEY"),
     *             @OA\Property(property="key", type="string", example="AIza..."),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Credential saved"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'key' => 'required|string',
            'value' => 'nullable|string',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        // Ownership comes from the authenticated context, never the request body.
        $organizationId = $request->user()->organization_id;
        $credential = ApiCredential::query()->updateOrCreate(
            ['organization_id' => $organizationId, 'provider' => $data['provider'], 'name' => $data['name']],
            $data
        );

        return response()->json([
            'status' => true,
            'message' => 'API Credential saved successfully',
            'data' => $credential
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/api-credentials/{id}",
     *     summary="Get credential details",
     *     operationId="getApiCredentialById",
     *     tags={"API Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Credential not found")
     * )
     */
    public function show($id)
    {
        $credential = ApiCredential::find($id);

        if (!$credential) {
            return response()->json([
                'status' => false,
                'message' => 'API Credential not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'API Credential retrieved successfully',
            'data' => $credential
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/api-credentials/{id}",
     *     summary="Update API credential",
     *     operationId="updateApiCredential",
     *     tags={"API Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(@OA\Property(property="is_active", type="boolean"))
     *     ),
     *     @OA\Response(response=200, description="Credential updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $credential = ApiCredential::find($id);

        if (!$credential) {
            return response()->json([
                'status' => false,
                'message' => 'API Credential not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'provider' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'key' => 'sometimes|required|string',
            'value' => 'nullable|string',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credential->update($validator->validated());

        return response()->json([
            'status' => true,
            'message' => 'API Credential updated successfully',
            'data' => $credential
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/api-credentials/{id}",
     *     summary="Delete API credential",
     *     operationId="deleteApiCredential",
     *     tags={"API Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Credential deleted")
     * )
     */
    public function destroy($id)
    {
        $credential = ApiCredential::find($id);

        if (!$credential) {
            return response()->json([
                'status' => false,
                'message' => 'API Credential not found'
            ], 404);
        }

        $credential->delete();

        return response()->json([
            'status' => true,
            'message' => 'API Credential deleted successfully'
        ]);
    }
}
