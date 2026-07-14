<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des produits récupérée avec succès',
            'data' => Product::with('category', 'partner')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'label_products' => 'required|string|max:250',
            'price' => 'required|numeric|min:0',
            'id_partners' => 'required|exists:partners,id_partners',
            'id_category' => 'required|exists:category,id_category',
        ]);

        $product = Product::create([
            'label_products' => $request->label_products,
            'price' => $request->price,
            'id_partners' => $request->id_partners,
            'id_category' => $request->id_category,
            'state' => $request->state ?? 'ACTIVE',
            'created_by' => $request->created_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('category', 'partner')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Produit récupéré avec succès',
            'data' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'label_products' => 'sometimes|required|string|max:250',
            'price' => 'sometimes|required|numeric|min:0',
            'id_partners' => 'sometimes|required|exists:partners,id_partners',
            'id_category' => 'sometimes|required|exists:category,id_category',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        $product->update([
            'label_products' => $request->label_products ?? $product->label_products,
            'price' => $request->price ?? $product->price,
            'id_partners' => $request->id_partners ?? $product->id_partners,
            'id_category' => $request->id_category ?? $product->id_category,
            'state' => $request->state ?? $product->state,
            'updated_by' => $request->updated_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);

        $product->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }
}