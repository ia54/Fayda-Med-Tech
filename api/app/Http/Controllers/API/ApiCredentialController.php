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

        // Use query() to start a fresh builder and explicitly remove global scopes
        // to ensure we can see both tenant-specific and system-wide credentials.
        $query = ApiCredential::query()->withoutGlobalScopes();
        
        if ($user->role !== 'admin') {
            // For non-admins, show their org's credentials AND system-wide ones (null org)
            $query->where(function($q) use ($user) {
                $q->where('organization_id', $user->organization_id)
                  ->orWhereNull('organization_id');
            });
        }
        
        $credentials = $query->latest()->get();
        
        // We might want to mask the keys in the list for security
        $credentials->map(function ($item) {
            if ($item->key) {
                $item->key_masked = substr($item->key, 0, 4) . '...' . substr($item->key, -4);
            }
            return $item;
        });

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

        // Use withoutGlobalScopes here so we can find existing credentials
        // regardless of the current tenant context during updateOrCreate.
        $credential = ApiCredential::query()->withoutGlobalScopes()->updateOrCreate(
            ['provider' => $request->provider, 'name' => $request->name],
            $request->all()
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

        $credential->update($request->all());

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
