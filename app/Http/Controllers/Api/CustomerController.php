<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Liste des clients selon le rôle.
     * ADMIN    : voit tous les clients
     * PARTNER  : voit uniquement les clients ayant commandé ses produits
     * CUSTOMER : voit uniquement son propre profil client
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        if ($user->role === 'ADMIN') {
            $customers = Customer::with([
                'user',
                'orders.orderDetails.product.partner',
                'orders.payments',
                'orders.deliveries'
            ])->get();

        } elseif ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $customers = Customer::with([
                'user',
                'orders.orderDetails.product.partner',
                'orders.payments',
                'orders.deliveries'
            ])
            ->whereHas('orders.orderDetails.product', function ($query) use ($partnerIds) {
                $query->whereIn('id_partners', $partnerIds);
            })
            ->get();

        } else {
            $customers = Customer::with([
                'user',
                'orders.orderDetails.product.partner',
                'orders.payments',
                'orders.deliveries'
            ])
            ->where('id_users', $user->id)
            ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des clients récupérée avec succès',
            'data' => $customers
        ]);
    }

    /**
     * Création d'un client.
     * ADMIN uniquement.
     * Pour un client normal, il faut utiliser /api/auth/register.
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

        if ($user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seul un administrateur peut créer un client depuis cette route. Utilisez /api/auth/register pour une inscription client.'
            ], 403);
        }

        $request->validate([
            'id_users' => 'nullable|exists:users,id',
            'first_name_customers' => 'required|string|max:250',
            'last_name_customers' => 'required|string|max:250',
            'phone_customers' => 'required|string|max:250',
            'mail_customers' => 'nullable|email|max:250',
            'status' => 'nullable|string|max:50',
        ]);

        $customer = Customer::create([
            'id_users' => $request->id_users,
            'first_name_customers' => $request->first_name_customers,
            'last_name_customers' => $request->last_name_customers,
            'phone_customers' => $request->phone_customers,
            'mail_customers' => $request->mail_customers,
            'status' => $request->status ?? 'ACTIVE',
            'created_by' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès',
            'data' => $customer
        ], 201);
    }

    /**
     * Afficher un client selon le rôle.
     * ADMIN    : peut voir tous les clients
     * PARTNER  : peut voir uniquement les clients ayant commandé ses produits
     * CUSTOMER : peut voir uniquement son propre profil
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.'
            ], 401);
        }

        $customer = Customer::with([
            'user',
            'orders.orderDetails.product.partner',
            'orders.payments',
            'orders.deliveries'
        ])->findOrFail($id);

        if ($user->role === 'ADMIN') {
            return response()->json([
                'success' => true,
                'message' => 'Client récupéré avec succès',
                'data' => $customer
            ]);
        }

        if ($user->role === 'CUSTOMER') {
            if ((int) $customer->id_users !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à ce profil client.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Client récupéré avec succès',
                'data' => $customer
            ]);
        }

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasOrderForPartner = $customer->orders
                ->contains(function ($order) use ($partnerIds) {
                    return $order->orderDetails
                        ->contains(function ($detail) use ($partnerIds) {
                            return $detail->product
                                && in_array((int) $detail->product->id_partners, $partnerIds);
                        });
                });

            if (!$hasOrderForPartner) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à ce client.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Client récupéré avec succès',
                'data' => $customer
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Rôle utilisateur non autorisé.'
        ], 403);
    }

    /**
     * Mise à jour d'un client.
     * ADMIN    : peut modifier tous les clients
     * CUSTOMER : peut modifier uniquement son propre profil
     * PARTNER  : ne peut pas modifier un client
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

        $customer = Customer::findOrFail($id);

        if ($user->role === 'PARTNER') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Un partenaire ne peut pas modifier un client.'
            ], 403);
        }

        if ($user->role === 'CUSTOMER') {
            if ((int) $customer->id_users !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit. Vous ne pouvez modifier que votre propre profil.'
                ], 403);
            }
        }

        if (!in_array($user->role, ['ADMIN', 'CUSTOMER'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        $request->validate([
            'first_name_customers' => 'sometimes|required|string|max:250',
            'last_name_customers' => 'sometimes|required|string|max:250',
            'phone_customers' => 'sometimes|required|string|max:250',
            'mail_customers' => 'sometimes|nullable|email|max:250',
            'status' => 'sometimes|nullable|string|max:50',
        ]);

        $customer->update([
            'first_name_customers' => $request->first_name_customers ?? $customer->first_name_customers,
            'last_name_customers' => $request->last_name_customers ?? $customer->last_name_customers,
            'phone_customers' => $request->phone_customers ?? $customer->phone_customers,
            'mail_customers' => $request->mail_customers ?? $customer->mail_customers,
            'status' => $user->role === 'ADMIN'
                ? ($request->status ?? $customer->status)
                : $customer->status,
            'updated_by' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès',
            'data' => $customer
        ]);
    }

    /**
     * Suppression logique d'un client.
     * ADMIN uniquement.
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

        if ($user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seul un administrateur peut supprimer un client.'
            ], 403);
        }

        $customer = Customer::findOrFail($id);

        $customer->update([
            'deleted_by' => $user->email,
        ]);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé avec succès'
        ]);
    }
}