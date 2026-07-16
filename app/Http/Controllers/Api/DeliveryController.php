<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryDetail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Display a listing of deliveries.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des livraisons récupérée avec succès',
            'data' => Delivery::with([
                'order.customer',
                'order.orderDetails.product',
                'deliveryDetails'
            ])->get()
        ]);
    }

    /**
     * Store a newly created delivery.
     */
    public function store(Request $request)
    {
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

            $deliveryStatus = $request->status ?? 'PENDING';

            $delivery = Delivery::create([
                'reference' => $reference,
                'area_delivery' => $request->area_delivery,
                'status' => strtoupper($deliveryStatus),
                'state' => 'ACTIVE',
                'id_orders' => $order->id_orders,
                'created_by' => $request->created_by ?? 'SYSTEM',
            ]);

            foreach ($order->orderDetails as $detail) {
                DeliveryDetail::create([
                    'id_delivery' => $delivery->id_delivery,
                    'product' => $detail->product->label_products ?? 'Produit inconnu',
                    'status' => strtoupper($deliveryStatus),
                    'state' => 'ACTIVE',
                    'created_by' => $request->created_by ?? 'SYSTEM',
                ]);
            }

            $order->update([
                'status_delivery' => strtoupper($deliveryStatus),
                'updated_by' => $request->updated_by ?? 'SYSTEM',
            ]);

            DB::commit();

            $delivery->load([
                'order.customer',
                'order.orderDetails.product',
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
     * Display the specified delivery.
     */
    public function show(string $id)
    {
        $delivery = Delivery::with([
            'order.customer',
            'order.orderDetails.product',
            'deliveryDetails'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison récupérée avec succès',
            'data' => $delivery
        ]);
    }

    /**
     * Update the specified delivery.
     */
    public function update(Request $request, string $id)
    {
        $delivery = Delivery::findOrFail($id);

        $request->validate([
            'area_delivery' => 'sometimes|nullable|string|max:250',
            'status' => 'sometimes|nullable|string|max:50',
            'state' => 'sometimes|nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            $newStatus = $request->status
                ? strtoupper($request->status)
                : $delivery->status;

            $delivery->update([
                'area_delivery' => $request->area_delivery ?? $delivery->area_delivery,
                'status' => $newStatus,
                'state' => $request->state ?? $delivery->state,
                'updated_by' => $request->updated_by ?? 'SYSTEM',
            ]);

            DeliveryDetail::where('id_delivery', $delivery->id_delivery)
                ->update([
                    'status' => $newStatus,
                    'updated_by' => $request->updated_by ?? 'SYSTEM',
                    'updated_at' => now(),
                ]);

            $order = Order::find($delivery->id_orders);

            if ($order) {
                $order->update([
                    'status_delivery' => $newStatus,
                    'updated_by' => $request->updated_by ?? 'SYSTEM',
                ]);
            }

            DB::commit();

            $delivery->load([
                'order.customer',
                'order.orderDetails.product',
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
     * Remove the specified delivery.
     */
    public function destroy(string $id)
    {
        $delivery = Delivery::findOrFail($id);

        $delivery->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $delivery->delete();

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès'
        ]);
    }
}