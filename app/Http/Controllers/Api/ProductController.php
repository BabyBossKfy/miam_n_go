<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Catalogue public : tout le monde peut voir les produits actifs.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des produits récupérée avec succès',
            'data' => Product::with('category', 'partner')
                ->where('state', 'ACTIVE')
                ->get()
        ]);
    }

    /**
     * Produits visibles par l'utilisateur connecté.
     * ADMIN voit tout.
     * PARTNER voit uniquement ses produits.
     */
    public function myProducts(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        if ($user->role === 'ADMIN') {
            $products = Product::with('category', 'partner')->get();

        } elseif ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $products = Product::with('category', 'partner')
                ->whereIn('id_partners', $partnerIds)
                ->get();

        } else {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Réservé aux partenaires ou administrateurs.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des produits utilisateur récupérée avec succès',
            'data' => $products
        ]);
    }

    /**
     * Création d'un produit.
     * ADMIN peut créer pour n'importe quel partenaire.
     * PARTNER peut créer uniquement pour son propre partenaire.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        if (!in_array($user->role, ['ADMIN', 'PARTNER'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous n’avez pas le droit de créer un produit.'
            ], 403);
        }

        $request->validate([
            'label_products' => 'required|string|max:250',
            'price' => 'required|numeric|min:0',
            'id_category' => 'required|exists:category,id_category',
            'id_partners' => 'required|exists:partners,id_partners',
        ]);

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            if (!in_array((int) $request->id_partners, $partnerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous ne pouvez créer un produit que pour votre propre partenaire.'
                ], 403);
            }
        }

        $product = Product::create([
            'label_products' => $request->label_products,
            'price' => $request->price,
            'id_category' => $request->id_category,
            'id_partners' => $request->id_partners,
            'state' => $request->state ?? 'ACTIVE',
            'created_by' => $request->created_by ?? $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product
        ], 201);
    }

    /**
     * Voir un produit spécifique.
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
     * Mise à jour d'un produit.
     * ADMIN peut modifier tous les produits.
     * PARTNER peut modifier uniquement ses produits.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        if (!in_array($user->role, ['ADMIN', 'PARTNER'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous n’avez pas le droit de modifier un produit.'
            ], 403);
        }

        $product = Product::findOrFail($id);

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            if (!in_array((int) $product->id_partners, $partnerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Ce produit ne vous appartient pas.'
                ], 403);
            }
        }

        $request->validate([
            'label_products' => 'sometimes|required|string|max:250',
            'price' => 'sometimes|required|numeric|min:0',
            'id_category' => 'sometimes|required|exists:category,id_category',
            'id_partners' => 'sometimes|required|exists:partners,id_partners',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        if ($user->role === 'PARTNER' && $request->has('id_partners')) {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            if (!in_array((int) $request->id_partners, $partnerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous ne pouvez pas affecter ce produit à un autre partenaire.'
                ], 403);
            }
        }

        $product->update([
            'label_products' => $request->label_products ?? $product->label_products,
            'price' => $request->price ?? $product->price,
            'id_category' => $request->id_category ?? $product->id_category,
            'id_partners' => $request->id_partners ?? $product->id_partners,
            'state' => $request->state ?? $product->state,
            'updated_by' => $request->updated_by ?? $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => $product
        ]);
    }

    /**
     * Suppression logique d'un produit.
     * ADMIN peut supprimer tous les produits.
     * PARTNER peut supprimer uniquement ses produits.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        if (!in_array($user->role, ['ADMIN', 'PARTNER'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous n’avez pas le droit de supprimer un produit.'
            ], 403);
        }

        $product = Product::findOrFail($id);

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            if (!in_array((int) $product->id_partners, $partnerIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Ce produit ne vous appartient pas.'
                ], 403);
            }
        }

        $product->update([
            'deleted_by' => $user->email,
        ]);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }
}