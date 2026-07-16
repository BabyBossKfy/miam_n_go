<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Liste des commandes selon le rôle.
     * ADMIN    : voit toutes les commandes
     * PARTNER  : voit les commandes contenant ses produits
     * CUSTOMER : voit uniquement ses commandes
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
            $orders = Order::with([
                'customer.user',
                'orderDetails.product.partner',
                'payments',
                'deliveries'
            ])->get();

        } elseif ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $orders = Order::with([
                'customer.user',
                'orderDetails.product.partner',
                'payments',
                'deliveries'
            ])
            ->whereHas('orderDetails.product', function ($query) use ($partnerIds) {
                $query->whereIn('id_partners', $partnerIds);
            })
            ->get();

        } else {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            $orders = Order::with([
                'customer.user',
                'orderDetails.product.partner',
                'payments',
                'deliveries'
            ])
            ->where('id_customers', $customer->id_customers)
            ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des commandes récupérée avec succès',
            'data' => $orders
        ]);
    }

    /**
     * Création d'une commande.
     * CUSTOMER : commande automatiquement liée au client connecté
     * ADMIN    : peut créer pour un client donné via id_customers
     * PARTNER  : non autorisé à créer une commande client
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

        if ($user->role === 'PARTNER') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Un partenaire ne peut pas créer une commande client.'
            ], 403);
        }

        if ($user->role === 'ADMIN') {
            $request->validate([
                'id_customers' => 'required|exists:customers,id_customers',
                'products' => 'required|array|min:1',
                'products.*.id_products' => 'required|exists:products,id_products',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            $customer = Customer::findOrFail($request->id_customers);

        } else {
            $request->validate([
                'products' => 'required|array|min:1',
                'products.*.id_products' => 'required|exists:products,id_products',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            $customer = Customer::where('id_users', $user->id)->firstOrFail();
        }

        try {
            DB::beginTransaction();

            $totalOrderPrice = 0;
            $orderItems = [];

            foreach ($request->products as $item) {
                $product = Product::findOrFail($item['id_products']);

                $quantity = $item['quantity'];
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;

                $totalOrderPrice += $totalPrice;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            $reference = 'CMD-' . date('YmdHis');

            $order = Order::create([
                'reference' => $reference,
                'price' => $totalOrderPrice,
                'status_order' => 'PENDING',
                'status_delivery' => 'PENDING',
                'status_payment' => 'PENDING',
                'state' => 'ACTIVE',
                'id_customers' => $customer->id_customers,
                'created_by' => $user->email,
            ]);

            foreach ($orderItems as $item) {
                OrderDetail::create([
                    'id_orders' => $order->id_orders,
                    'id_products' => $item['product']->id_products,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'status_order' => 'PENDING',
                    'status_delivery' => 'PENDING',
                    'status_payment' => 'PENDING',
                    'state' => 'ACTIVE',
                    'created_by' => $user->email,
                ]);
            }

            DB::commit();

            $order->load([
                'customer.user',
                'orderDetails.product.partner',
                'payments',
                'deliveries'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une commande selon le rôle.
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

        $order = Order::with([
            'customer.user',
            'orderDetails.product.partner',
            'payments',
            'deliveries'
        ])->findOrFail($id);

        if ($user->role === 'ADMIN') {
            return response()->json([
                'success' => true,
                'message' => 'Commande récupérée avec succès',
                'data' => $order
            ]);
        }

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasPartnerProduct = $order->orderDetails
                ->contains(function ($detail) use ($partnerIds) {
                    return $detail->product
                        && in_array((int) $detail->product->id_partners, $partnerIds);
                });

            if (!$hasPartnerProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à cette commande.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande récupérée avec succès',
                'data' => $order
            ]);
        }

        $customer = Customer::where('id_users', $user->id)->firstOrFail();

        if ((int) $order->id_customers !== (int) $customer->id_customers) {
            return response()->json([
                'success' => false,
                'message' => 'Accès interdit à cette commande.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commande récupérée avec succès',
            'data' => $order
        ]);
    }

    /**
     * Mise à jour d'une commande.
     * ADMIN    : peut tout modifier
     * PARTNER  : peut modifier uniquement status_order sur ses commandes
     * CUSTOMER : non autorisé à modifier directement
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

        $order = Order::with('orderDetails.product')->findOrFail($id);

        if ($user->role === 'CUSTOMER') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Un client ne peut pas modifier directement une commande.'
            ], 403);
        }

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasPartnerProduct = $order->orderDetails
                ->contains(function ($detail) use ($partnerIds) {
                    return $detail->product
                        && in_array((int) $detail->product->id_partners, $partnerIds);
                });

            if (!$hasPartnerProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit. Cette commande ne concerne pas votre partenaire.'
                ], 403);
            }

            $request->validate([
                'status_order' => 'sometimes|nullable|string|max:50',
            ]);

            $order->update([
                'status_order' => $request->status_order ?? $order->status_order,
                'updated_by' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut de commande mis à jour avec succès',
                'data' => $order
            ]);
        }

        $request->validate([
            'status_order' => 'sometimes|nullable|string|max:50',
            'status_delivery' => 'sometimes|nullable|string|max:50',
            'status_payment' => 'sometimes|nullable|string|max:50',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        $order->update([
            'status_order' => $request->status_order ?? $order->status_order,
            'status_delivery' => $request->status_delivery ?? $order->status_delivery,
            'status_payment' => $request->status_payment ?? $order->status_payment,
            'state' => $request->state ?? $order->state,
            'updated_by' => $user->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande mise à jour avec succès',
            'data' => $order
        ]);
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
                'message' => 'Accès refusé. Seul un administrateur peut supprimer une commande.'
            ], 403);
        }

        $order = Order::findOrFail($id);

        $order->update([
            'deleted_by' => $user->email,
        ]);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée avec succès'
        ]);
    }
}