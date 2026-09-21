<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Testimonials",
 *     description="API Endpoints for Customer Success Stories"
 * )
 */
class TestimonialController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/testimonials",
     *     summary="Get all testimonials",
     *     operationId="getTestimonials",
     *     tags={"Testimonials"},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $testimonials = Testimonial::when($request->status, function ($q) use ($request){
           return $q->where('status',$request->status);
        })->paginate($request->limit ?? 10);
        return response()->json([
            'status' => true,
            'testimonials' => $testimonials
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/testimonial",
     *     summary="Create a new testimonial",
     *     operationId="createTestimonial",
     *     tags={"Testimonials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating", "comment", "commented_by", "position"},
     *             @OA\Property(property="rating", type="integer", enum={1,2,3,4,5}),
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="commented_by", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Testimonial created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|in:1,2,3,4,5',
            'comment' => 'required|string',
            'commented_by' => 'required|string|max:255',
            'position' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $testimonial = new Testimonial();
        $testimonial->rating = $request->rating;
        $testimonial->comment = $request->comment;
        $testimonial->commented_by = $request->commented_by;
        $testimonial->position = $request->position;
        $testimonial->status = $request->status;
        $testimonial->save();
        return response()->json([
            'status' => true,
            'message' => 'Testimonial created successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/testimonial/{id}",
     *     summary="Update testimonial",
     *     operationId="updateTestimonial",
     *     tags={"Testimonials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating", "comment", "commented_by", "position"},
     *             @OA\Property(property="rating", type="integer", enum={1,2,3,4,5}),
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="commented_by", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="status", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Testimonial updated")
     * )
     */
    public function update(Request $request,$id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|in:1,2,3,4,5',
            'comment' => 'required|string',
            'commented_by' => 'required|string|max:255',
            'position' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $testimonial = Testimonial::find($id);
        $testimonial->rating = $request->rating;
        $testimonial->comment = $request->comment;
        $testimonial->commented_by = $request->commented_by;
        $testimonial->position = $request->position;
        $testimonial->status = $request->status;
        $testimonial->save();
        return response()->json([
            'status' => true,
            'message' => 'Testimonial updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/testimonial/{id}",
     *     summary="Delete testimonial",
     *     operationId="deleteTestimonial",
     *     tags={"Testimonials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Testimonial deleted")
     * )
     */
    public function destroy($id)
    {
        Testimonial::find($id)->delete();
        return response()->json([
            'status' => true,
            'message' => 'Testimonial deleted successfully'
        ]);
    }
}
