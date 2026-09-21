<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FirmOrganizationController extends Controller
{
    /**
     * Display the firm's organization details.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $organization = Organization::find($user->organization_id);

            if (!$organization) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Organization details retrieved successfully',
                'data' => $organization
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve organization details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the firm's organization details.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            $organization = Organization::find($user->organization_id);

            if (!$organization) {
                return response()->json([
                    'status' => false,
                    'message' => 'Organization not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'org_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:organizations,email,' . $organization->id,
                'no_of_employees' => 'nullable|integer|min:1',
                'monthly_revenue' => 'nullable|numeric|min:0',
                'yearly_revenue' => 'nullable|numeric|min:0',
                'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'primary_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                'secondary_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
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
                $supportDocs = $organization->support_documents ?? [];
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
                'message' => 'Organization details updated successfully',
                'data' => $organization->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update organization details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
