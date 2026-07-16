<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Liste des paiements selon le rôle.
     * ADMIN    : voit tous les paiements
     * PARTNER  : voit les paiements des commandes contenant ses produits
     * CUSTOMER : voit uniquement les paiements de ses commandes
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
            $payments = Payment::with([
                'order.customer.user',
                'order.orderDetails.product.partner'
            ])->get();

        } elseif ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $payments = Payment::with([
                'order.customer.user',
                'order.orderDetails.product.partner'
            ])
            ->whereHas('order.orderDetails.product', function ($query) use ($partnerIds) {
                $query->whereIn('id_partners', $partnerIds);
            })
            ->get();

        } else {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            $payments = Payment::with([
                'order.customer.user',
                'order.orderDetails.product.partner'
            ])
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('id_customers', $customer->id_customers);
            })
            ->get();
        }

        return response()->json([
            'success' => true,
            'message' => 'Liste des paiements récupérée avec succès',
            'data' => $payments
        ]);
    }

    /**
     * Création d'un paiement.
     * ADMIN    : peut payer n'importe quelle commande
     * CUSTOMER : peut payer uniquement ses propres commandes
     * PARTNER  : non autorisé à créer un paiement
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
                'message' => 'Accès refusé. Un partenaire ne peut pas créer un paiement.'
            ], 403);
        }

        $request->validate([
            'id_orders' => 'required|exists:orders,id_orders',
            'type' => 'required|string|max:250',
            'phone_payment' => 'nullable|string|max:50',
            'transaction' => 'nullable|string|max:250',
            'token' => 'nullable|string|max:250',
            'response' => 'nullable|string|max:50',
            'status_transaction' => 'required|string|max:250',
        ]);

        $order = Order::with('customer')
            ->findOrFail($request->id_orders);

        if ($user->role === 'CUSTOMER') {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            if ((int) $order->id_customers !== (int) $customer->id_customers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit. Vous ne pouvez payer que vos propres commandes.'
                ], 403);
            }
        }

        try {
            DB::beginTransaction();

            $reference = 'PAY-' . date('YmdHis');

            $statusTransaction = strtoupper($request->status_transaction);

            if (in_array($statusTransaction, ['SUCCESS', 'PAID', 'VALIDATED'])) {
                $paymentStatus = 'PAID';
                $orderPaymentStatus = 'PAID';
            } elseif (in_array($statusTransaction, ['FAILED', 'CANCELLED', 'REJECTED'])) {
                $paymentStatus = 'FAILED';
                $orderPaymentStatus = 'FAILED';
            } else {
                $paymentStatus = 'PENDING';
                $orderPaymentStatus = 'PENDING';
            }

            $payment = Payment::create([
                'reference' => $reference,
                'transaction' => $request->transaction,
                'type' => strtoupper($request->type),
                'phone_payment' => $request->phone_payment,
                'token' => $request->token,
                'response' => $request->response,
                'status_transaction' => $statusTransaction,
                'status' => $paymentStatus,
                'state' => 'ACTIVE',
                'id_orders' => $order->id_orders,
                'created_by' => $user->email,
            ]);

            $order->update([
                'status_payment' => $orderPaymentStatus,
                'updated_by' => $user->email,
            ]);

            DB::commit();

            $payment->load([
                'order.customer.user',
                'order.orderDetails.product.partner'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => $payment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l’enregistrement du paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un paiement selon le rôle.
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

        $payment = Payment::with([
            'order.customer.user',
            'order.orderDetails.product.partner'
        ])->findOrFail($id);

        if ($user->role === 'ADMIN') {
            return response()->json([
                'success' => true,
                'message' => 'Paiement récupéré avec succès',
                'data' => $payment
            ]);
        }

        if ($user->role === 'CUSTOMER') {
            $customer = Customer::where('id_users', $user->id)->firstOrFail();

            if ((int) $payment->order->id_customers !== (int) $customer->id_customers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à ce paiement.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement récupéré avec succès',
                'data' => $payment
            ]);
        }

        if ($user->role === 'PARTNER') {
            $partnerIds = $user->partners()
                ->pluck('partners.id_partners')
                ->toArray();

            $hasPartnerProduct = $payment->order->orderDetails
                ->contains(function ($detail) use ($partnerIds) {
                    return $detail->product
                        && in_array((int) $detail->product->id_partners, $partnerIds);
                });

            if (!$hasPartnerProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès interdit à ce paiement.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paiement récupéré avec succès',
                'data' => $payment
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Rôle utilisateur non autorisé.'
        ], 403);
    }

    /**
     * Mise à jour du paiement.
     * ADMIN uniquement.
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

        if ($user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seul un administrateur peut modifier un paiement.'
            ], 403);
        }

        $payment = Payment::findOrFail($id);

        $request->validate([
            'transaction' => 'sometimes|nullable|string|max:250',
            'type' => 'sometimes|nullable|string|max:250',
            'phone_payment' => 'sometimes|nullable|string|max:50',
            'token' => 'sometimes|nullable|string|max:250',
            'response' => 'sometimes|nullable|string|max:50',
            'status_transaction' => 'sometimes|nullable|string|max:250',
            'status' => 'sometimes|nullable|string|max:50',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            $payment->update([
                'transaction' => $request->transaction ?? $payment->transaction,
                'type' => $request->type ? strtoupper($request->type) : $payment->type,
                'phone_payment' => $request->phone_payment ?? $payment->phone_payment,
                'token' => $request->token ?? $payment->token,
                'response' => $request->response ?? $payment->response,
                'status_transaction' => $request->status_transaction
                    ? strtoupper($request->status_transaction)
                    : $payment->status_transaction,
                'status' => $request->status
                    ? strtoupper($request->status)
                    : $payment->status,
                'state' => $request->state ?? $payment->state,
                'updated_by' => $user->email,
            ]);

            $order = Order::find($payment->id_orders);

            if ($order && $request->status) {
                $order->update([
                    'status_payment' => strtoupper($request->status),
                    'updated_by' => $user->email,
                ]);
            }

            DB::commit();

            $payment->load([
                'order.customer.user',
                'order.orderDetails.product.partner'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement mis à jour avec succès',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du paiement',
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
                'message' => 'Accès refusé. Seul un administrateur peut supprimer un paiement.'
            ], 403);
        }

        $payment = Payment::findOrFail($id);

        $payment->update([
            'deleted_by' => $user->email,
        ]);

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paiement supprimé avec succès'
        ]);
    }
}