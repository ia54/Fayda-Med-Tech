<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Http\Requests\PageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * @OA\Get(
     *      path="/pages",
     *      operationId="getPagesList",
     *      tags={"Pages"},
     *      summary="Get list of pages",
     *      description="Returns list of pages",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       )
     * )
     */
    public function index(): JsonResponse
    {
        $pages = Page::with('seo')->get(); // Basic list with SEO maybe
        return response()->json($pages);
    }

    /**
     * @OA\Post(
     *      path="/pages",
     *      operationId="storePage",
     *      tags={"Pages"},
     *      summary="Store new page",
     *      description="Returns page data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"title","slug"},
     *              @OA\Property(property="title", type="string", example="About Us"),
     *              @OA\Property(property="slug", type="string", example="about-us")
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
    public function store(PageRequest $request): JsonResponse
    {
        // Simple page creation, blocks/fields usually added separately or via a dedicated complex endpoint if needed.
        // User asked "make api for fatch full pages data , blocks and fields".
        $page = Page::create($request->validated());
        return response()->json($page, 201);
    }

    /**
     * @OA\Get(
     *      path="/pages/{id}",
     *      operationId="getPageById",
     *      tags={"Pages"},
     *      summary="Get page information with blocks and fields",
     *      description="Returns page data and nested blocks and fields",
     *      @OA\Parameter(
     *          name="id",
     *          description="Page id",
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
    public function show(Page $page): JsonResponse
    {
        $page->load(['seo', 'blocks.fields']);
        return response()->json($page);
    }

    /**
     * @OA\Put(
     *      path="/pages/{id}",
     *      operationId="updatePage",
     *      tags={"Pages"},
     *      summary="Update existing page",
     *      description="Returns updated page data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Page id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"title","slug"},
     *              @OA\Property(property="title", type="string", example="About Us Updated"),
     *              @OA\Property(property="slug", type="string", example="about-us")
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
    public function update(PageRequest $request, Page $page): JsonResponse
    {
        $page->update($request->validated());
        return response()->json($page);
    }

    /**
     * @OA\Delete(
     *      path="/pages/{id}",
     *      operationId="deletePage",
     *      tags={"Pages"},
     *      summary="Delete existing page",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Page id",
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
    public function destroy(Page $page): JsonResponse
    {
        $page->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *      path="/pages/query/blocks",
     *      operationId="queryBlocks",
     *      tags={"Pages"},
     *      summary="Block wise query",
     *      description="Fetch blocks with filtering",
     *      @OA\Parameter(
     *          name="page_id",
     *          in="query",
     *          description="Filter by page ID",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="slug",
     *          in="query",
     *          description="Filter by block slug",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="List of blocks"
     *      )
     * )
     */
    public function queryBlocks(Request $request): JsonResponse
    {
        $query = \App\Models\Block::with('fields');

        if ($request->has('page_id')) {
            $query->where('page_id', $request->input('page_id'));
        }

        if ($request->has('slug')) {
            $query->where('slug', $request->input('slug'));
        }

        return response()->json($query->get());
    }
}
