<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Liste des livraisons selon le rôle.
     * ADMIN    : voit toutes les livraisons
     * PARTNER  : voit les livraisons des commandes contenant ses produits
     * CUSTOMER : voit uniquement les livraisons de ses commandes
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
            $deliveries = Delivery::with([
                'order.customer.user',
                'order.orderDetails.product.partner',
                'deliveryDetails'
            ])->get();

        } elseif ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $deliveries = Delivery::with([
                'order.customer.user',
                'order.orderDetails.product.partner',
                'deliveryDetails'
            ])
            ->whereHas('order.orderDetails.product', function ($query) use ($partnerIds) {
                $query->whereIn('id_partners', $partnerIds);
            })
            ->get();

        } else {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            $deliveries = Delivery::with([
                'order.customer.user',
                'order.orderDetails.product.partner',
                'deliveryDetails'
            ])
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('id_customers', $customer->id_customers);
            })
            ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des livraisons récupérée avec succès',
            'data' => $deliveries
        ]);
    }

    /**
     * Création d'une livraison.
     * ADMIN uniquement.
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
                'message' => 'Accès refusé. Seul un administrateur peut créer une livraison.'
            ], 403);
        }

        $request->validate([
            'id_orders' => 'required|exists:orders,id_orders',
            'area_delivery' => 'required|string|max:250',
            'status' => 'nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::with('orderDetails.product')
                ->findOrFail($request->id_orders);

            $reference = 'LIV-' . date('YmdHis');
            $deliveryStatus = strtoupper($request->status ?? 'PENDING');

            $delivery = Delivery::create([
                'reference' => $reference,
                'area_delivery' => $request->area_delivery,
                'status' => $deliveryStatus,
                'state' => 'ACTIVE',
                'id_orders' => $order->id_orders,
                'created_by' => $user->email,
            ]);

            foreach ($order->orderDetails as $detail) {
                DeliveryDetail::create([
                    'id_delivery' => $delivery->id_delivery,
                    'product' => $detail->product->label_products ?? 'Produit inconnu',
                    'status' => $deliveryStatus,
                    'state' => 'ACTIVE',
                    'created_by' => $user->email,
                ]);
            }

            $order->update([
                'status_delivery' => $deliveryStatus,
                'updated_by' => $user->email,
            ]);

            DB::commit();

            $delivery->load([
                'order.customer.user',
                'order.orderDetails.product.partner',
                'deliveryDetails'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'data' => $delivery
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une livraison selon le rôle.
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

        $delivery = Delivery::with([
            'order.customer.user',
            'order.orderDetails.product.partner',
            'deliveryDetails'
        ])->findOrFail($id);

        if ($user->role === 'ADMIN') {
            return response()->json([
                'success' => true,
                'message' => 'Livraison récupérée avec succès',
                'data' => $delivery
            ]);
        }

        if ($user->role === 'CUSTOMER') {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            if ((int) $delivery->order->id_customers !== (int) $customer->id_customers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à cette livraison.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Livraison récupérée avec succès',
                'data' => $delivery
            ]);
        }

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasPartnerProduct = $delivery->order->orderDetails
                ->contains(function ($detail) use ($partnerIds) {
                    return $detail->product
                        && in_array((int) $detail->product->id_partners, $partnerIds);
                });

            if (!$hasPartnerProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à cette livraison.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Livraison récupérée avec succès',
                'data' => $delivery
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Rôle utilisateur non autorisé.'
        ], 403);
    }

    /**
     * Mise à jour d'une livraison.
     * ADMIN   : peut tout modifier
     * PARTNER : peut modifier uniquement le statut si la livraison concerne ses produits
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

        if ($user->role === 'CUSTOMER') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Un client ne peut pas modifier une livraison.'
            ], 403);
        }

        $delivery = Delivery::with([
            'order.orderDetails.product'
        ])->findOrFail($id);

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasPartnerProduct = $delivery->order->orderDetails
                ->contains(function ($detail) use ($partnerIds) {
                    return $detail->product
                        && in_array((int) $detail->product->id_partners, $partnerIds);
                });

            if (!$hasPartnerProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit. Cette livraison ne concerne pas votre partenaire.'
                ], 403);
            }

            $request->validate([
                'status' => 'sometimes|required|string|max:50',
            ]);

        } else {
            $request->validate([
                'area_delivery' => 'sometimes|nullable|string|max:250',
                'status' => 'sometimes|nullable|string|max:50',
                'state' => 'sometimes|nullable|string|max:50',
            ]);
        }

        try {
            DB::beginTransaction();

            $newStatus = $request->status
                ? strtoupper($request->status)
                : $delivery->status;

            $delivery->update([
                'area_delivery' => $user->role === 'ADMIN'
                    ? ($request->area_delivery ?? $delivery->area_delivery)
                    : $delivery->area_delivery,
                'status' => $newStatus,
                'state' => $user->role === 'ADMIN'
                    ? ($request->state ?? $delivery->state)
                    : $delivery->state,
                'updated_by' => $user->email,
            ]);

            DeliveryDetail::where('id_delivery', $delivery->id_delivery)
                ->update([
                    'status' => $newStatus,
                    'updated_by' => $user->email,
                    'updated_at' => now(),
                ]);

            $order = Order::find($delivery->id_orders);

            if ($order) {
                $order->update([
                    'status_delivery' => $newStatus,
                    'updated_by' => $user->email,
                ]);
            }

            DB::commit();

            $delivery->load([
                'order.customer.user',
                'order.orderDetails.product.partner',
                'deliveryDetails'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Livraison mise à jour avec succès',
                'data' => $delivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suppression logique.
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
                'message' => 'Accès refusé. Seul un administrateur peut supprimer une livraison.'
            ], 403);
        }

        $delivery = Delivery::findOrFail($id);

        $delivery->update([
            'deleted_by' => $user->email,
        ]);

        $delivery->delete();

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès'
        ]);
    }
}