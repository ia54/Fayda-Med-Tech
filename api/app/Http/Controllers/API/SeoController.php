<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Seo;
use App\Models\Page;
use App\Http\Requests\SeoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    /**
     * @OA\Get(
     *      path="/pages/{page}/seo",
     *      operationId="getPageSeo",
     *      tags={"SEO"},
     *      summary="Get SEO for a page",
     *      description="Returns SEO data",
     *      @OA\Parameter(
     *          name="page",
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
        return response()->json($page->seo);
    }

    /**
     * @OA\Put(
     *      path="/pages/{page}/seo",
     *      operationId="updatePageSeo",
     *      tags={"SEO"},
     *      summary="Update SEO for a page",
     *      description="Returns updated SEO data",
     *      @OA\Parameter(
     *          name="page",
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
     *              example={
     *                  "title": "Page Title",
     *                  "description": "Page Description",
     *                  "image": "http://site.com/img.jpg",
     *                  "keywords": "keyword1, keyword2"
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request"
     *      )
     * )
     */
    public function update(SeoRequest $request, Page $page): JsonResponse
    {
        $seoData = $page->seo()->updateOrCreate(
            ['page_id' => $page->id],
            $request->validated()
        );

        return response()->json($seoData);
    }
}
