<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des paiements récupérée avec succès',
            'data' => Payment::with('order')->get()
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_orders' => 'required|exists:orders,id_orders',
            'type' => 'required|string|max:250',
            'transaction' => 'nullable|string|max:250',
            'token' => 'nullable|string|max:250',
            'response' => 'nullable|string|max:50',
            'phone_payment' => 'nullable|string|max:50',
            'status_transaction' => 'required|string|max:250',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($request->id_orders);

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
                'created_by' => $request->created_by ?? 'SYSTEM',
            ]);

            $order->update([
                'status_payment' => $orderPaymentStatus,
                'updated_by' => $request->updated_by ?? 'SYSTEM',
            ]);

            DB::commit();

            $payment->load('order');

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
     * Display the specified payment.
     */
    public function show(string $id)
    {
        $payment = Payment::with('order')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Paiement récupéré avec succès',
            'data' => $payment
        ]);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);

        $request->validate([
            'transaction' => 'sometimes|nullable|string|max:250',
            'type' => 'sometimes|nullable|string|max:250',
            'token' => 'sometimes|nullable|string|max:250',
            'phone_payment' => 'sometimes|nullable|string|max:50',
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
                'token' => $request->token ?? $payment->token,
                'phone_payment' => $request->phone_payment ?? $payment->phone_payment,
                'response' => $request->response ?? $payment->response,
                'status_transaction' => $request->status_transaction
                    ? strtoupper($request->status_transaction)
                    : $payment->status_transaction,
                'status' => $request->status
                    ? strtoupper($request->status)
                    : $payment->status,
                'state' => $request->state ?? $payment->state,
                'updated_by' => $request->updated_by ?? 'SYSTEM',
            ]);

            $order = Order::find($payment->id_orders);

            if ($order && $request->status) {
                $order->update([
                    'status_payment' => strtoupper($request->status),
                    'updated_by' => $request->updated_by ?? 'SYSTEM',
                ]);
            }

            DB::commit();

            $payment->load('order');

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
     * Remove the specified payment.
     */
    public function destroy(string $id)
    {
        $payment = Payment::findOrFail($id);

        $payment->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paiement supprimé avec succès'
        ]);
    }
}