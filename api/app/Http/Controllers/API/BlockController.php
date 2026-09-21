<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Http\Requests\BlockRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    /**
     * @OA\Get(
     *      path="/blocks",
     *      operationId="getBlocksList",
     *      tags={"Blocks"},
     *      summary="Get list of blocks",
     *      description="Returns list of blocks. Use page_id to filter.",
     *      @OA\Parameter(
     *          name="page_id",
     *          in="query",
     *          description="Filter by Page ID",
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
        $query = Block::query();

        if ($request->has('page_id')) {
            $query->where('page_id', $request->input('page_id'));
        }

        $blocks = $query->with('fields')->get();
        return response()->json($blocks);
    }

    /**
     * @OA\Post(
     *      path="/api/blocks",
     *      operationId="storeBlock",
     *      tags={"Blocks"},
     *      summary="Store new block",
     *      description="Returns block data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"page_id","title","slug"},
     *              @OA\Property(property="page_id", type="integer", example=1),
     *              @OA\Property(property="title", type="string", example="Hero Section"),
     *              @OA\Property(property="slug", type="string", example="hero-section")
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
    public function store(BlockRequest $request): JsonResponse
    {
        $block = Block::create($request->validated());
        return response()->json($block, 201);
    }

    /**
     * @OA\Get(
     *      path="/api/blocks/{id}",
     *      operationId="getBlockById",
     *      tags={"Blocks"},
     *      summary="Get block information",
     *      description="Returns block data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Block id",
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
    public function show(Block $block): JsonResponse
    {
        $block->load('fields');
        return response()->json($block);
    }

    /**
     * @OA\Put(
     *      path="/api/blocks/{id}",
     *      operationId="updateBlock",
     *      tags={"Blocks"},
     *      summary="Update existing block",
     *      description="Returns updated block data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Block id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"page_id","title","slug"},
     *              @OA\Property(property="page_id", type="integer", example=1),
     *              @OA\Property(property="title", type="string", example="Hero Section Updated"),
     *              @OA\Property(property="slug", type="string", example="hero-section")
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
    public function update(BlockRequest $request, Block $block): JsonResponse
    {
        $block->update($request->validated());
        return response()->json($block);
    }

    /**
     * @OA\Delete(
     *      path="/api/blocks/{id}",
     *      operationId="deleteBlock",
     *      tags={"Blocks"},
     *      summary="Delete existing block",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Block id",
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
    public function destroy(Block $block): JsonResponse
    {
        $block->delete();
        return response()->json(null, 204);
    }
}
