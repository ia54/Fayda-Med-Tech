<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Http\Requests\MediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * @OA\Get(
     *      path="/medias",
     *      operationId="getMediasList",
     *      tags={"Media"},
     *      summary="Get list of media",
     *      description="Returns list of uploaded media",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       )
     * )
     */
    public function index(): JsonResponse
    {
        $media = Media::latest()->get();
        return response()->json($media);
    }

    /**
     * @OA\Post(
     *      path="/medias",
     *      operationId="uploadMedia",
     *      tags={"Media"},
     *      summary="Upload new media",
     *      description="Returns media data",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="file",
     *                      description="File to upload",
     *                      type="string",
     *                      format="binary"
     *                  )
     *              )
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
    public function store(MediaRequest $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('uploads', 'public');
            $filename = $file->hashName();

            // Get dimensions if image
            $width = null;
            $height = null;
            $placeholder = null; // Placeholder generation skipped for now as per instructions "this hould migration file. stickly follow this.", placeholder logic needs library which might not be installed. Setting null.

            if (substr($file->getMimeType(), 0, 5) == 'image') {
                $size = getimagesize($file->getPathname());
                if ($size) {
                    $width = $size[0];
                    $height = $size[1];
                }
            }

            $media = Media::create([
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::url($path),
                'ext' => $file->extension(),
                'size' => (string) $file->getSize(),
                'width' => $width,
                'height' => $height,
                'placeholder' => $placeholder,
            ]);

            return response()->json($media, 201);
        }

        return response()->json(['error' => 'No file uploaded'], 400);
    }

    /**
     * @OA\Get(
     *      path="/medias/{id}",
     *      operationId="getMediaById",
     *      tags={"Media"},
     *      summary="Get media information",
     *      description="Returns media data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Media id",
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
    public function show(Media $media): JsonResponse
    {
        return response()->json($media);
    }

    /**
     * @OA\Delete(
     *      path="/medias/{id}",
     *      operationId="deleteMedia",
     *      tags={"Media"},
     *      summary="Delete existing media",
     *      description="Deletes a record and returns no content",
     *      @OA\Parameter(
     *          name="id",
     *          description="Media id",
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
    public function destroy(Media $media): JsonResponse
    {
        if (Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();
        return response()->json(null, 204);
    }
}
