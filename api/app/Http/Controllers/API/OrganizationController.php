<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/organizations",
     *     summary="Get all organizations",
     *     description="Retrieve a list of all organizations (Admin only)",
     *     operationId="getOrganizations",
     *     tags={"Organizations"},
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
     *         description="Organizations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organizations retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="organizations", type="array", @OA\Items(ref="#/components/schemas/Organization")),
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
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $organizations = Organization::when($request->org_type,function ($q) use ($request){
            return $q->where('org_type',$request->org_type);
        })->when($request->subscription_plan,function ($q) use ($request){
            return $q->where('subscription_plan',$request->subscription_plan);
        })->when($request->org_name,function ($q) use ($request){
            return $q->where('org_name','like','%'.$request->org_name.'%');
        })->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Organizations retrieved successfully',
            'data' => [
                'organizations' => $organizations->items(),
                'pagination' => [
                    'current_page' => $organizations->currentPage(),
                    'per_page' => $organizations->perPage(),
                    'total' => $organizations->total(),
                    'last_page' => $organizations->lastPage(),
                ]
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/organizations",
     *     summary="Create a new organization",
     *     description="Create a new organization with all required details (Admin only)",
     *     operationId="createOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Organization data",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"org_name","org_type","subscription_plan","email"},
     *                 @OA\Property(property="org_name", type="string", example="Faydamed Healthcare"),
     *                 @OA\Property(property="org_type", type="string", example="Healthcare Provider"),
     *                 @OA\Property(property="subscription_plan", type="string", example="Premium"),
     *                 @OA\Property(property="email", type="string", format="email", example="info@faydamed.com"),
     *                 @OA\Property(property="no_of_employees", type="integer", example=50),
     *                 @OA\Property(property="monthly_revenue", type="number", format="float", example=50000.00),
     *                 @OA\Property(property="yearly_revenue", type="number", format="float", example=600000.00),
     *                 @OA\Property(property="company_logo", type="string", format="binary", description="Company logo image"),
     *                 @OA\Property(property="tax_bin_no", type="string", example="123456789"),
     *                 @OA\Property(property="support_documents[]", type="array", @OA\Items(type="string", format="binary"), description="Support documents (optional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'org_name' => 'required|string|max:255',
            'org_type' => 'required|string|max:255',
            'subscription_plan' => 'required|string|max:255',
            'email' => 'required|string|email|unique:organizations|max:255',
            'no_of_employees' => 'nullable|integer|min:1',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'yearly_revenue' => 'nullable|numeric|min:0',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'tax_bin_no' => 'nullable|string|max:255',
            'support_documents.*' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['company_logo', 'support_documents']);

        // Handle company logo upload
        if ($request->hasFile('company_logo')) {
            $logo = $request->file('company_logo');
            $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
            $logoPath = $logo->storeAs('organizations/logos', $logoName, 'public');
            $data['company_logo'] = $logoPath;
        }

        // Handle support documents upload
        if ($request->hasFile('support_documents')) {
            $supportDocs = [];
            foreach ($request->file('support_documents') as $doc) {
                $docName = time() . '_' . uniqid() . '.' . $doc->getClientOriginalExtension();
                $docPath = $doc->storeAs('organizations/documents', $docName, 'public');
                $supportDocs[] = $docPath;
            }
            $data['support_documents'] = $supportDocs;
        }

        $organization = Organization::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Organization created successfully',
            'data' => $organization
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{id}",
     *     summary="Get organization by ID",
     *     description="Retrieve a specific organization by ID (Admin only)",
     *     operationId="getOrganizationById",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show($id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Organization retrieved successfully',
            'data' => $organization
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{id}",
     *     summary="Update organization",
     *     description="Update an existing organization (Admin only). Use POST with _method=PUT for file uploads",
     *     operationId="updateOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Organization data to update",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT", description="HTTP method override"),
     *                 @OA\Property(property="org_name", type="string", example="Faydamed Healthcare"),
     *                 @OA\Property(property="org_type", type="string", example="Healthcare Provider"),
     *                 @OA\Property(property="subscription_plan", type="string", example="Premium"),
     *                 @OA\Property(property="email", type="string", format="email", example="info@faydamed.com"),
     *                 @OA\Property(property="no_of_employees", type="integer", example=50),
     *                 @OA\Property(property="monthly_revenue", type="number", format="float", example=50000.00),
     *                 @OA\Property(property="yearly_revenue", type="number", format="float", example=600000.00),
     *                 @OA\Property(property="company_logo", type="string", format="binary", description="Company logo image"),
     *                 @OA\Property(property="tax_bin_no", type="string", example="123456789"),
     *                 @OA\Property(property="support_documents[]", type="array", @OA\Items(type="string", format="binary"), description="Support documents (optional)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Organization")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, $id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'org_name' => 'sometimes|required|string|max:255',
            'org_type' => 'sometimes|required|string|max:255',
            'subscription_plan' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:organizations,email,' . $id,
            'no_of_employees' => 'nullable|integer|min:1',
            'monthly_revenue' => 'nullable|numeric|min:0',
            'yearly_revenue' => 'nullable|numeric|min:0',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'tax_bin_no' => 'nullable|string|max:255',
            'support_documents.*' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['company_logo', 'support_documents', '_method']);

        // Handle company logo upload
        if ($request->hasFile('company_logo')) {
            // Delete old logo if exists
            if ($organization->company_logo && Storage::disk('public')->exists($organization->company_logo)) {
                Storage::disk('public')->delete($organization->company_logo);
            }

            $logo = $request->file('company_logo');
            $logoName = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
            $logoPath = $logo->storeAs('organizations/logos', $logoName, 'public');
            $data['company_logo'] = $logoPath;
        }

        // Handle support documents upload
        if ($request->hasFile('support_documents')) {
            // Delete old documents if exists
            if ($organization->support_documents) {
                foreach ($organization->support_documents as $oldDoc) {
                    if (Storage::disk('public')->exists($oldDoc)) {
                        Storage::disk('public')->delete($oldDoc);
                    }
                }
            }

            $supportDocs = [];
            foreach ($request->file('support_documents') as $doc) {
                $docName = time() . '_' . uniqid() . '.' . $doc->getClientOriginalExtension();
                $docPath = $doc->storeAs('organizations/documents', $docName, 'public');
                $supportDocs[] = $docPath;
            }
            $data['support_documents'] = $supportDocs;
        }

        $organization->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Organization updated successfully',
            'data' => $organization->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{id}",
     *     summary="Delete organization",
     *     description="Delete an organization by ID (Admin only)",
     *     operationId="deleteOrganization",
     *     tags={"Organizations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Organization ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Organization deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy($id)
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'status' => false,
                'message' => 'Organization not found'
            ], 404);
        }

        // Delete company logo if exists
        if ($organization->company_logo && Storage::disk('public')->exists($organization->company_logo)) {
            Storage::disk('public')->delete($organization->company_logo);
        }

        // Delete support documents if exists
        if ($organization->support_documents) {
            foreach ($organization->support_documents as $doc) {
                if (Storage::disk('public')->exists($doc)) {
                    Storage::disk('public')->delete($doc);
                }
            }
        }

        $organization->delete();

        return response()->json([
            'status' => true,
            'message' => 'Organization deleted successfully'
        ]);
    }
}
