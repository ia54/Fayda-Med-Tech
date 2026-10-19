<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class InsuranceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/insurance/companies",
     *     summary="List insurance companies",
     *     tags={"Insurance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $data = $request->validate(['search'=>'nullable|string|max:200', 'page'=>'nullable|integer|min:1', 'per_page'=>'nullable|integer|min:1|max:100']);
        $query = InsuranceCompany::where('organization_id', $request->user()->organization_id);
        if (!empty($data['search'])) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['search'].'%')->orWhere('email', 'like', '%'.$data['search'].'%'));
        }
        $companies = $query->orderBy('name')->paginate($data['per_page'] ?? 15);

        return response()->json([
            'status' => true,
            'message' => 'Insurance companies retrieved successfully',
            'data' => $companies
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/insurance/companies",
     *     summary="Create insurance company",
     *     tags={"Insurance Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'claims_office_address' => 'nullable|string',
            'payment_rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $company = InsuranceCompany::create([
            'organization_id' => $request->user()->organization_id,
            ...$request->only(['name', 'phone', 'email', 'address', 'city', 'state', 'zip_code', 'claims_office_address', 'payment_rating', 'notes'])
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Insurance company created successfully',
            'data' => $company
        ], 201);
    }

    /**
     * Display the specified insurance company.
     */
    public function show($id)
    {
        abort_unless(request()->user()->organization_id, 403);
        $company = InsuranceCompany::where('organization_id', request()->user()->organization_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Insurance company retrieved successfully',
            'data' => $company
        ]);
    }

    /**
     * Update the specified insurance company.
     */
    public function update(Request $request, $id)
    {
        abort_unless($request->user()->organization_id, 403);
        $company = InsuranceCompany::where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'claims_office_address' => 'nullable|string',
            'payment_rating' => 'nullable|numeric|min:0|max:5',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $company->update($request->only(['name', 'phone', 'email', 'address', 'city', 'state', 'zip_code', 'claims_office_address', 'payment_rating', 'notes']));

        return response()->json([
            'status' => true,
            'message' => 'Insurance company updated successfully',
            'data' => $company
        ]);
    }

    /**
     * Remove the specified insurance company.
     */
    public function destroy($id)
    {
        abort_unless(request()->user()->organization_id, 403);
        DB::transaction(function () use ($id) {
            $company = InsuranceCompany::where('organization_id', request()->user()->organization_id)->lockForUpdate()->findOrFail($id);
            abort_if($company->claims()->exists() || $company->adjusters()->exists(), 409, 'This carrier has linked claims or adjusters and must be retained.');
            $company->delete();
        });

        return response()->json([
            'status' => true,
            'message' => 'Insurance company deleted successfully'
        ]);
    }
}
