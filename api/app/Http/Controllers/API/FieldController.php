<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Field;
use App\Http\Requests\FieldRequest;
use Illuminate\Http\JsonResponse;

class FieldController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/fields",
     *      operationId="getFieldsList",
     *      tags={"Fields"},
     *      summary="Get list of fields",
     *      description="Returns list of fields. Use block_id to filter.",
     *      @OA\Parameter(
     *          name="block_id",
     *          in="query",
     *          description="Filter by Block ID",
     *          required=false,
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Field::query();

        if ($request->has('block_id')) {
            $query->where('block_id', $request->input('block_id'));
        }

        $fields = $query->get();
        return response()->json($fields);
    }

    /**
     * @OA\Post(
     *      path="/api/fields",
     *      operationId="storeField",
     *      tags={"Fields"},
     *      summary="Store new field",
     *      description="Returns field data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"block_id","type","name","label"},
     *              @OA\Property(property="block_id", type="integer", example=1),
     *              @OA\Property(property="type", type="string", example="text"),
     *              @OA\Property(property="name", type="string", example="headline"),
     *              @OA\Property(property="label", type="string", example="Headline"),
     *              @OA\Property(property="value", type="string", example="Welcome"),
     *              @OA\Property(property="options", type="object", example={"placeholder": "Enter text"}),
     *              @OA\Property(property="required", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function store(FieldRequest $request): JsonResponse
    {
        $field = Field::create($request->validated());
        return response()->json($field, 201);
    }

    /**
     * @OA\Get(
     *      path="/fields/{id}",
     *      operationId="getFieldById",
     *      tags={"Fields"},
     *      summary="Get field information",
     *      description="Returns field data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Field id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function show(Field $field): JsonResponse
    {
        return response()->json($field);
    }

    /**
     * @OA\Put(
     *      path="/fields/{id}",
     *      operationId="updateField",
     *      tags={"Fields"},
     *      summary="Update existing field",
     *      description="Returns updated field data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Field id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"block_id","type","name","label"},
     *              @OA\Property(property="block_id", type="integer", example=1),
     *              @OA\Property(property="type", type="string", example="text"),
     *              @OA\Property(property="name", type="string", example="headline"),
     *              @OA\Property(property="label", type="string", example="Headline"),
     *              @OA\Property(property="value", type="string", example="Welcome"),
     *              @OA\Property(property="options", type="object", example={"placeholder": "Enter text"}),
     *              @OA\Property(property="required", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function update(FieldRequest $request, Field $field): JsonResponse
    {
        $field->update($request->validated());
        return response()->json($field);
    }

    /**
     * @OA\Delete(
     *      path="/fields/{id}",
     *      operationId="deleteField",
     *      tags={"Fields"},
     *      summary="Delete existing field",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Field id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=204,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=404,
     *          description="Resource Not Found"
     *      )
     * )
     */
    public function destroy(Field $field): JsonResponse
    {
        $field->delete();
        return response()->json(null, 204);
    }
}
