<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="FAQ",
 *     description="API Endpoints for Frequently Asked Questions"
 * )
 */
class FaqController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/faqs",
     *     summary="Get all FAQs",
     *     operationId="getFaqs",
     *     tags={"FAQ"},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $faqs = Faq::paginate($request->limit ?? 10);
        return response()->json([
            'status' => true,
            'faqs' => $faqs
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/faq",
     *     summary="Create a new FAQ",
     *     operationId="createFaq",
     *     tags={"FAQ"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"question", "answer"},
     *             @OA\Property(property="question", type="string"),
     *             @OA\Property(property="answer", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="FAQ created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255|unique:faqs,question',
            'answer' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $faq = new Faq();
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->save();
        return response()->json([
            'status' => true,
            'message' => 'Faq created successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/faq/{id}",
     *     summary="Update FAQ",
     *     operationId="updateFaq",
     *     tags={"FAQ"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"question", "answer"},
     *             @OA\Property(property="question", type="string"),
     *             @OA\Property(property="answer", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="FAQ updated")
     * )
     */
    public function update(Request $request,$id)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:255|unique:faqs,question,'.$id,
            'answer' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $faq = Faq::find($id);
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->save();
        return response()->json([
            'status' => true,
            'message' => 'Faq updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/faq/{id}",
     *     summary="Delete FAQ",
     *     operationId="deleteFaq",
     *     tags={"FAQ"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="FAQ deleted")
     * )
     */
    public function destroy($id)
    {
        Faq::find($id)->delete();
        return response()->json([
            'status' => true,
            'message' => 'Faq deleted successfully'
        ]);
    }
}
