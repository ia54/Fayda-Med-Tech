<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CaseModel;
use App\Http\Resources\CaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Cases",
 *     description="API Endpoints for Legal Case Management"
 * )
 */
use App\Traits\LogsTimeline;

class CaseController extends Controller
{
    use LogsTimeline;
    /**
     * @OA\Get(
     *     path="/api/cases",
     *     summary="Get all cases",
     *     description="Retrieve a list of all legal cases for the organization",
     *     operationId="getCases",
     *     tags={"Cases"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", description="Search by title or case number", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = CaseModel::query();
        $user = $request->user();

        // Role-based restrictions
        if ($user->role === 'attorney') {
            $query->assignedToAttorney($user->id);
        } elseif ($user->role === 'client') {
            $query->forClient($user->id);
        }

        // Basic filtering
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('case_number', 'like', "%$search%")
                  ->orWhere('title', 'like', "%$search%");
            });
        }

        $cases = $query->latest()->paginate($request->get('per_page', 15));

        return CaseResource::collection($cases);
    }

    /**
     * @OA\Post(
     *     path="/api/cases",
     *     summary="Create a new case",
     *     description="Initialize a new legal matter",
     *     operationId="createCase",
     *     tags={"Cases"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="title", type="string", example="Doe vs Insurance"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="status", type="string", enum={"New", "Intake", "Active", "Demand", "Settlement", "Closed"}),
     *             @OA\Property(property="accident_date", type="string", format="date"),
     *             @OA\Property(property="sol_date", type="string", format="date"),
     *             @OA\Property(property="jurisdiction", type="string"),
     *             @OA\Property(property="total_case_value", type="number")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Case created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:New,Intake,Active,Demand,Settlement,Closed',
            'accident_date' => 'nullable|date',
            'sol_date' => 'nullable|date',
            'jurisdiction' => 'nullable|string|max:255',
            'total_case_value' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $case = CaseModel::create([
            'case_number' => $this->generateCaseNumber(),
            'title' => $request->title,
            'description' => $request->description,
            'status' => $request->status ?? 'New',
            'accident_date' => $request->accident_date,
            'sol_date' => $request->sol_date,
            'jurisdiction' => $request->jurisdiction,
            'total_case_value' => $request->total_case_value ?? 0,
            'created_by' => $user->id,
            'metadata' => $request->metadata,
        ]);

        // If an attorney creates the case, automatically assign them
        if ($user->role === 'attorney') {
            $case->parties()->create([
                'user_id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role_in_case' => 'attorney',
            ]);
        }

        // Log to timeline
        $this->logTimeline(
            $case->id,
            'milestone',
            'Case Created',
            "Case '{$case->title}' has been successfully initialized in the system.",
            ['case_number' => $case->case_number]
        );

        return new CaseResource($case);
    }

    /**
     * @OA\Get(
     *     path="/api/cases/{id}",
     *     summary="Get case details",
     *     description="Retrieve full details of a specific case",
     *     operationId="getCaseById",
     *     tags={"Cases"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Case not found")
     * )
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $query = CaseModel::with(['creator', 'parties', 'notes.user', 'tasks.assignedTo', 'timeline.user']);

        if ($user->role === 'attorney') {
            $query->assignedToAttorney($user->id);
        } elseif ($user->role === 'client') {
            $query->forClient($user->id);
        }

        $case = $query->findOrFail($id);
        return new CaseResource($case);
    }

    /**
     * @OA\Put(
     *     path="/api/cases/{id}",
     *     summary="Update a case",
     *     description="Update details of an existing case",
     *     operationId="updateCase",
     *     tags={"Cases"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Case updated"),
     *     @OA\Response(response=404, description="Case not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        abort_if($user->role !== 'admin' && !$user->organization_id, 403);
        $query = CaseModel::query();

        if ($user->role === 'attorney') {
            $query->assignedToAttorney($user->id);
        } elseif ($user->role === 'client') {
            $query->forClient($user->id);
        }

        $case = $query->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:New,Intake,Active,Demand,Settlement,Closed',
            'accident_date' => 'nullable|date',
            'sol_date' => 'nullable|date',
            'jurisdiction' => 'nullable|string|max:255',
            'total_case_value' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($case, $validator) {
            $case->refresh();
            $oldStatus = $case->status;
            $case->update($validator->validated());
            $newStatus = $case->fresh()->status;

            if ($oldStatus !== $newStatus) {
                $this->logTimeline(
                    $case->id,
                    'milestone',
                    "Status Changed: {$newStatus}",
                    "The case status has been updated from '{$oldStatus}' to '{$newStatus}'.",
                    ['old_status' => $oldStatus, 'new_status' => $newStatus]
                );
            }

        });

        return new CaseResource($case);
    }

    /**
     * @OA\Delete(
     *     path="/api/cases/{id}",
     *     summary="Delete a case",
     *     description="Remove a case from the system",
     *     operationId="deleteCase",
     *     tags={"Cases"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Case deleted"),
     *     @OA\Response(response=404, description="Case not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $query = CaseModel::query();

        if ($user->role === 'attorney') {
            $query->assignedToAttorney($user->id);
        } elseif ($user->role === 'client') {
            $query->forClient($user->id);
        }

        $case = $query->findOrFail($id);
        $case->delete();

        return response()->json([
            'status' => true,
            'message' => 'Case deleted successfully'
        ]);
    }

    /**
     * Add a party to the case.
     */
    public function addParty(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'role_in_case' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $case = CaseModel::findOrFail($id);
        
        // Auto-link to user if email matches and user_id is not provided
        $userId = $request->user_id;
        if (!$userId && $request->email) {
            $user = \App\Models\User::where('email', $request->email)->first();
            if ($user) {
                $userId = $user->id;
            }
        }

        $party = $case->parties()->create([
            'user_id' => $userId,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role_in_case' => $request->role_in_case,
        ]);

        // Log to timeline
        $this->logTimeline(
            $case->id,
            'milestone',
            'Party Added',
            "New party '{$party->name}' has been added to the case as '{$party->role_in_case}'.",
            ['party_name' => $party->name, 'role' => $party->role_in_case]
        );

        return response()->json([
            'status' => true,
            'message' => 'Party added successfully',
            'data' => $party
        ], 201);
    }

    /**
     * Generate a unique case number.
     */
    private function generateCaseNumber()
    {
        $prefix = 'CASE-' . date('Y') . '-';
        $latest = CaseModel::withoutGlobalScopes()
            ->where('case_number', 'like', $prefix . '%')
            ->orderBy('case_number', 'desc')
            ->first();

        if ($latest) {
            $number = intval(substr($latest->case_number, strrpos($latest->case_number, '-') + 1)) + 1;
        } else {
            $number = 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
