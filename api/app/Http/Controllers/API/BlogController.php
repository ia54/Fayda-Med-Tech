<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/blogs",
     *     summary="Get all blogs",
     *     description="Retrieve a list of all blogs (Admin only)",
     *     operationId="getAllBlogs",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "published", "schedule"})
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blogs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blogs retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Blog"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = Blog::query();

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $blogs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => true,
                'message' => 'Blogs retrieved successfully',
                'data' => $blogs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve blogs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/blogs",
     *     summary="Create a new blog",
     *     description="Create a new blog post (Admin only)",
     *     operationId="createBlog",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "author", "category", "content"},
     *                 @OA\Property(property="title", type="string", example="Introduction to Laravel"),
     *                 @OA\Property(property="url_slug", type="string", example="introduction-to-laravel", description="Auto-generated from title if not provided"),
     *                 @OA\Property(property="author", type="string", example="John Doe"),
     *                 @OA\Property(property="category", type="string", example="Technology"),
     *                 @OA\Property(property="excerpt", type="string", example="A brief introduction to Laravel framework"),
     *                 @OA\Property(property="content", type="string", example="Laravel is a web application framework..."),
     *                 @OA\Property(property="tags", type="string", example="[""laravel"",""php"",""framework""]", description="JSON string array of tags"),
     *                 @OA\Property(property="status", type="string", enum={"draft", "published", "schedule"}, example="draft"),
     *                 @OA\Property(property="published_at", type="string", format="date-time", example="2025-11-10T10:00:00Z", description="Required for schedule status"),
     *                 @OA\Property(property="blog_images[]", type="array", @OA\Items(type="string", format="binary"), description="Multiple blog images")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blog created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Blog")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request)
    {
        try {
            // Pre-process tags if sent as JSON string (from Swagger/form-data)
            $requestData = $request->all();
            if (isset($requestData['tags']) && is_string($requestData['tags'])) {
                $decodedTags = json_decode($requestData['tags'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)) {
                    $requestData['tags'] = $decodedTags;
                    $request->merge(['tags' => $decodedTags]); // Merge back into request
                }
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'url_slug' => 'nullable|string|max:255|unique:blogs,url_slug',
                'author' => 'required|string|max:255',
                'category' => 'required|string|max:255',
                'excerpt' => 'nullable|string',
                'content' => 'required|string',
                'tags' => 'nullable|array',
                'tags.*' => 'string',
                'status' => 'nullable|in:draft,published,schedule',
                'published_at' => 'nullable|date',
                'blog_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except('blog_images');

            // Auto-generate URL slug if not provided
            if (!$request->has('url_slug') || empty($request->url_slug)) {
                $data['url_slug'] = Str::slug($request->title) . '-' . time();
            }

            // Default status to draft
            if (!$request->has('status')) {
                $data['status'] = 'draft';
            }

            // Handle multiple blog images upload
            if ($request->hasFile('blog_images')) {
                $blogImages = [];
                foreach ($request->file('blog_images') as $image) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('blogs/images', $imageName, 'public');
                    $blogImages[] = $imagePath;
                }
                $data['blog_images'] = $blogImages;
            }

            $blog = Blog::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Blog created successfully',
                'data' => $blog
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create blog',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/blogs/{id}",
     *     summary="Get blog by ID",
     *     description="Retrieve a specific blog by ID (Admin only)",
     *     operationId="getBlogById",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Blog")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Blog not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show($id)
    {
        try {
            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Blog retrieved successfully',
                'data' => $blog
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve blog',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/blogs/{id}",
     *     summary="Update blog",
     *     description="Update an existing blog (Admin only). Use POST with _method=PUT for file uploads",
     *     operationId="updateBlog",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT", description="HTTP method override"),
     *                 @OA\Property(property="title", type="string", example="Introduction to Laravel"),
     *                 @OA\Property(property="url_slug", type="string", example="introduction-to-laravel"),
     *                 @OA\Property(property="author", type="string", example="John Doe"),
     *                 @OA\Property(property="category", type="string", example="Technology"),
     *                 @OA\Property(property="excerpt", type="string", example="A brief introduction to Laravel framework"),
     *                 @OA\Property(property="content", type="string", example="Laravel is a web application framework..."),
     *                 @OA\Property(property="tags", type="string", example="[""laravel"",""php"",""framework""]", description="JSON string array of tags"),
     *                 @OA\Property(property="status", type="string", enum={"draft", "published", "schedule"}, example="published"),
     *                 @OA\Property(property="published_at", type="string", format="date-time", example="2025-11-10T10:00:00Z"),
     *                 @OA\Property(property="blog_images[]", type="array", @OA\Items(type="string", format="binary"), description="Multiple blog images (replaces existing)"),
     *                 @OA\Property(property="keep_existing_images", type="boolean", example=false, description="Set to true to keep existing images")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Blog")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Blog not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            // Pre-process tags if sent as JSON string (from Swagger/form-data)
            $requestData = $request->all();
            if (isset($requestData['tags']) && is_string($requestData['tags'])) {
                $decodedTags = json_decode($requestData['tags'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)) {
                    $requestData['tags'] = $decodedTags;
                    $request->merge(['tags' => $decodedTags]); // Merge back into request
                }
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'url_slug' => 'sometimes|required|string|max:255|unique:blogs,url_slug,' . $id,
                'author' => 'sometimes|required|string|max:255',
                'category' => 'sometimes|required|string|max:255',
                'excerpt' => 'nullable|string',
                'content' => 'sometimes|required|string',
                'tags' => 'nullable|array',
                'tags.*' => 'string',
                'status' => 'nullable|in:draft,published,schedule',
                'published_at' => 'nullable|date',
                'blog_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
                'keep_existing_images' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->except(['blog_images', '_method', 'keep_existing_images']);

            // Handle multiple blog images upload
            if ($request->hasFile('blog_images')) {
                // Check if we should keep existing images (support multiple formats)
                $keepExisting = $request->input('keep_existing_images') === 'true' 
                             || $request->input('keep_existing_images') === '1' 
                             || $request->input('keep_existing_images') === 1
                             || $request->boolean('keep_existing_images');
                
                // Delete old images if not keeping them
                if (!$keepExisting && $blog->blog_images) {
                    foreach ($blog->blog_images as $oldImage) {
                        if (Storage::disk('public')->exists($oldImage)) {
                            Storage::disk('public')->delete($oldImage);
                        }
                    }
                }

                $blogImages = [];
                
                // Keep existing images if requested
                if ($keepExisting && $blog->blog_images) {
                    $blogImages = $blog->blog_images;
                }

                // Add new images
                foreach ($request->file('blog_images') as $image) {
                    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('blogs/images', $imageName, 'public');
                    $blogImages[] = $imagePath;
                }
                
                $data['blog_images'] = $blogImages;
            }

            $blog->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Blog updated successfully',
                'data' => $blog->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update blog',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/blogs/{id}",
     *     summary="Delete blog",
     *     description="Delete a blog by ID (Admin only)",
     *     operationId="deleteBlog",
     *     tags={"Blogs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blog ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blog deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blog deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Blog not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy($id)
    {
        try {
            $blog = Blog::find($id);

            if (!$blog) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blog not found'
                ], 404);
            }

            // Delete all blog images
            if ($blog->blog_images) {
                foreach ($blog->blog_images as $image) {
                    if (Storage::disk('public')->exists($image)) {
                        Storage::disk('public')->delete($image);
                    }
                }
            }

            $blog->delete();

            return response()->json([
                'status' => true,
                'message' => 'Blog deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete blog',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
