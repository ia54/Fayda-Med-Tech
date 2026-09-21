<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IpAllowlist;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="IP Allowlist",
 *     description="API Endpoints for Managing Trusted IP Addresses"
 * )
 */
class IpAllowlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/security/ip-allowlist",
     *     summary="List allowed IPs",
     *     operationId="getIpAllowlist",
     *     tags={"IP Allowlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index()
    {
        $ips = IpAllowlist::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'ips' => $ips
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/security/ip-allowlist",
     *     summary="Add IP to allowlist",
     *     operationId="storeIpAllowlist",
     *     tags={"IP Allowlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ip_address"},
     *             @OA\Property(property="ip_address", type="string", example="192.168.1.1"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="IP added")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $ip = IpAllowlist::create([
            'ip_address' => $request->ip_address,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'IP address added to allowlist',
            'ip' => $ip
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/security/ip-allowlist/{id}",
     *     summary="Update allowlist entry",
     *     operationId="updateIpAllowlist",
     *     tags={"IP Allowlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(@OA\Property(property="is_active", type="boolean"))
     *     ),
     *     @OA\Response(response=200, description="Entry updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $ip = IpAllowlist::findOrFail($id);
        $ip->update($request->only(['ip_address', 'description', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => 'IP allowlist entry updated',
            'ip' => $ip
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/security/ip-allowlist/{id}",
     *     summary="Remove IP from allowlist",
     *     operationId="deleteIpAllowlist",
     *     tags={"IP Allowlist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="IP removed")
     * )
     */
    public function destroy($id)
    {
        $ip = IpAllowlist::findOrFail($id);
        $ip->delete();

        return response()->json([
            'success' => true,
            'message' => 'IP address removed from allowlist'
        ]);
    }
}
