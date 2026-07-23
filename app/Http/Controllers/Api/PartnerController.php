<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des partenaires récupérée avec succès',
            'data' => Partner::with('products')->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'label_partners' => 'required|string|max:250'
        ]);

        $partner = Partner::create([
            'label_partners' => $request->label_partners,
            'state' => $request->state ?? 'ACTIVE',
            'created_by' => $request->user()->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partenaire créé avec succès',
            'data' => $partner
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $partner = Partner::with('products')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Partenaire récupéré avec succès',
            'data' => $partner
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $request->validate([
            'label_partners' => 'sometimes|required|string|max:250',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        $partner->update([
            'label_partners' => $request->label_partners ?? $partner->label_partners,
            'state' => $request->state ?? $partner->state,
            'updated_by' => $request->user()->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partenaire mis à jour avec succès',
            'data' => $partner
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $partner = Partner::findOrFail($id);

        $partner->update([
            'deleted_by' => $request->user()->email,
        ]);

        $partner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partenaire supprimé avec succès'
        ]);
    }
}