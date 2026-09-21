<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BlogCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/blog-categories",
     *     summary="Get all blog categories",
     *     description="Retrieve a list of all blog categories (Admin only)",
     *     operationId="getBlogCategories",
     *     tags={"Blog Categories"},
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
     *         description="Blog categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog categories retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="blog_categories", type="array", @OA\Items(ref="#/components/schemas/BlogCategory")),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=10),
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="last_page", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $blogCategories = BlogCategory::paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Blog categories retrieved successfully',
                'data' => [
                    'blog_categories' => $blogCategories->items(),
                    'pagination' => [
                        'current_page' => $blogCategories->currentPage(),
                        'per_page' => $blogCategories->perPage(),
                        'total' => $blogCategories->total(),
                        'last_page' => $blogCategories->lastPage(),
                        'has_more_pages' => $blogCategories->hasMorePages()
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve blog categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/blog-categories",
     *     summary="Create a new blog category",
     *     description="Create a new blog category (Admin only)",
     *     operationId="createBlogCategory",
     *     tags={"Blog Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"category"},
     *                 @OA\Property(property="category", type="string", example="Technology", description="Name of the blog category"),
     *                 @OA\Property(property="description", type="string", example="Articles about technology and innovation", description="Description of the blog category"),
     *                 @OA\Property(property="post", type="integer", example=0, description="Number of posts in this category")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blog category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog category created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BlogCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255|unique:blog_categories,category',
                'description' => 'nullable|string|max:1000',
                'post' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $blogCategory = BlogCategory::create([
                'category' => $request->category,
                'description' => $request->description,
                'post' => $request->post ?? 0,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Blog category created successfully',
                'data' => $blogCategory
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create blog category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/blog-categories/{id}",
     *     summary="Get a specific blog category",
     *     description="Retrieve a specific blog category by ID (Admin only)",
     *     operationId="getBlogCategory",
     *     tags={"Blog Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog category retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BlogCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blog category not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $blogCategory = BlogCategory::find($id);

            if (!$blogCategory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog category not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Blog category retrieved successfully',
                'data' => $blogCategory
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve blog category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/blog-categories/{id}",
     *     summary="Update a blog category",
     *     description="Update an existing blog category (Admin only)",
     *     operationId="updateBlogCategory",
     *     tags={"Blog Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"category"},
     *                 @OA\Property(property="category", type="string", example="Technology", description="Name of the blog category"),
     *                 @OA\Property(property="description", type="string", example="Articles about technology and innovation", description="Description of the blog category"),
     *                 @OA\Property(property="post", type="integer", example=5, description="Number of posts in this category")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog category updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BlogCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blog category not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $blogCategory = BlogCategory::find($id);

            if (!$blogCategory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog category not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'category' => 'required|string|max:255|unique:blog_categories,category,' . $id,
                'description' => 'nullable|string|max:1000',
                'post' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $blogCategory->update([
                'category' => $request->category,
                'description' => $request->description,
                'post' => $request->post ?? $blogCategory->post,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Blog category updated successfully',
                'data' => $blogCategory->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update blog category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/blog-categories/{id}",
     *     summary="Delete a blog category",
     *     description="Delete an existing blog category (Admin only)",
     *     operationId="deleteBlogCategory",
     *     tags={"Blog Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog category deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blog category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blog category not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Blog category is being used",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete blog category. It is being used by existing blogs.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied. Admin role required.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $blogCategory = BlogCategory::find($id);

            if (!$blogCategory) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog category not found'
                ], 404);
            }

            // Check if blog category is being used by any blogs
            $blogsCount = $blogCategory->blogs()->count();
            if ($blogsCount > 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete blog category. It is being used by ' . $blogsCount . ' blog(s).'
                ], 409);
            }

            $blogCategory->delete();

            return response()->json([
                'status' => true,
                'message' => 'Blog category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete blog category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
