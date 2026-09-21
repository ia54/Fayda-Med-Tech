<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\WhyWeDifferent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Why We Different",
 *     description="API Endpoints for Platform Advantages"
 * )
 */
class WhyWeDifferentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/whywedifferent",
     *     summary="Get platform advantages",
     *     operationId="getWhyWeDifferent",
     *     tags={"Why We Different"},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $whywediferents = WhyWeDifferent::when($request->status, function ($q) use ($request){
            return $q->where('status',$request->status);
        })->paginate($request->limit ?? 10);
        return response()->json([
            'status' => true,
            'whywediferents' => $whywediferents
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/whywedifferent",
     *     summary="Create a new advantage point",
     *     operationId="createWhyWeDifferent",
     *     tags={"Why We Different"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "subtitle"},
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="subtitle", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Advantage point created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $whywediferent = new WhyWeDifferent();
        $whywediferent->title = $request->title;
        $whywediferent->subtitle = $request->subtitle;
        $whywediferent->save();

        if ($request->file('image')){
            imageStoreUpdate('WhyWeDifferent',$request->image,$whywediferent->id);
        }
        return response()->json([
            'status' => true,
            'message' => 'whywediferent created successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/whywedifferent/{id}",
     *     summary="Update advantage point",
     *     description="Update an advantage point. Use POST with _method=PUT for image uploads",
     *     operationId="updateWhyWeDifferent",
     *     tags={"Why We Different"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="_method", type="string", example="PUT"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="subtitle", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Advantage point updated")
     * )
     */
    public function update(Request $request,$id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $whywediferent = WhyWeDifferent::find($id);
        $whywediferent->title = $request->title;
        $whywediferent->subtitle = $request->subtitle;
        $whywediferent->save();
        if ($request->file('image')){
            imageStoreUpdate('WhyWeDifferent',$request->image,$whywediferent->id);
        }

        return response()->json([
            'status' => true,
            'message' => 'whywediferent updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/whywedifferent/{id}",
     *     summary="Delete advantage point",
     *     operationId="deleteWhyWeDifferent",
     *     tags={"Why We Different"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Advantage point deleted")
     * )
     */
    public function destroy($id)
    {
        WhyWeDifferent::find($id)->delete();
        return response()->json([
            'status' => true,
            'message' => 'whywediferent deleted successfully'
        ]);
    }
}
