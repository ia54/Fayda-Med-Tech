<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProviderController extends Controller
{
    /**
     * List providers
     */
    public function index(Request $request)
    {
        $query = Provider::where('organization_id', $request->user()->organization_id);

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%")
                  ->orWhere('npi', 'like', "%{$request->search}%");
        }

        $providers = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'message' => 'Providers retrieved successfully',
            'data' => $providers
        ]);
    }

    /**
     * Store a new provider
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'npi' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'fee_schedule' => 'nullable|array',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $provider = Provider::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['name', 'npi', 'specialty', 'tax_id', 'phone', 'email', 'address', 'city', 'state', 'zip_code', 'fee_schedule', 'rating', 'notes'])
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Provider created successfully',
            'data' => $provider
        ], 201);
    }

    /**
     * Display the specified provider.
     */
    public function show($id)
    {
        $provider = Provider::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Provider retrieved successfully',
            'data' => $provider
        ]);
    }

    /**
     * Update the specified provider.
     */
    public function update(Request $request, $id)
    {
        $provider = Provider::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'npi' => 'nullable|string|max:20',
            'specialty' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'fee_schedule' => 'nullable|array',
            'rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $provider->update($request->only(['name', 'npi', 'specialty', 'tax_id', 'phone', 'email', 'address', 'city', 'state', 'zip_code', 'fee_schedule', 'rating', 'notes']));

        return response()->json([
            'status' => true,
            'message' => 'Provider updated successfully',
            'data' => $provider
        ]);
    }

    /**
     * Remove the specified provider.
     */
    public function destroy($id)
    {
        $provider = Provider::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        $provider->delete();

        return response()->json([
            'status' => true,
            'message' => 'Provider deleted successfully'
        ]);
    }
}
