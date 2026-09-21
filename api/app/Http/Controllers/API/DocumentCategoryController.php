<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class DocumentCategoryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => DocumentCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        $data['organization_id'] = $request->user()->organization_id;

        $category = DocumentCategory::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Category created',
            'data' => $category,
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'data' => DocumentCategory::findOrFail($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = DocumentCategory::findOrFail($id);
        $category->update($request->only(['name', 'description', 'color', 'icon', 'sort_order', 'is_active']));
        return response()->json(['status' => true, 'message' => 'Category updated', 'data' => $category->fresh()]);
    }

    public function destroy($id)
    {
        DocumentCategory::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Category deleted']);
    }
}