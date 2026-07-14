<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des catégories récupérée avec succès',
            'data' => Category::with('products')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'label_category' => 'required|string|max:250',
        ]);

        $category = Category::create([
            'label_category' => $request->label_category,
            'state' => $request->state ?? 'ACTIVE',
            'created_by' => $request->created_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès',
            'data' => $category
        ], 201);
    }

    public function show(string $id)
    {
        $category = Category::with('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie récupérée avec succès',
            'data' => $category
        ]);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'label_category' => 'sometimes|required|string|max:250',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        $category->update([
            'label_category' => $request->label_category ?? $category->label_category,
            'state' => $request->state ?? $category->state,
            'updated_by' => $request->updated_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour avec succès',
            'data' => $category
        ]);
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);

        $category->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }
}