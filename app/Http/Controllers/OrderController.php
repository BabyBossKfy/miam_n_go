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
     * Display a listing of orders.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des commandes récupérée avec succès',
            'data' => Order::with([
                'customer',
                'orderDetails.product'
            ])->get()
        ]);
    }

    /**
     * Store a newly created order with order details.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_customers' => 'required|exists:customers,id_customers',
            'products' => 'required|array|min:1',
            'products.*.id_products' => 'required|exists:products,id_products',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $customer = Customer::findOrFail($request->id_customers);

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
                'created_by' => $request->created_by ?? 'SYSTEM',
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
                    'created_by' => $request->created_by ?? 'SYSTEM',
                ]);
            }

            DB::commit();

            $order->load([
                'customer',
                'orderDetails.product'
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
     * Display the specified order.
     */
    public function show(string $id)
    {
        $order = Order::with([
            'customer',
            'orderDetails.product'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Commande récupérée avec succès',
            'data' => $order
        ]);
    }

    /**
     * Update the specified order.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::findOrFail($id);

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
            'updated_by' => $request->updated_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande mise à jour avec succès',
            'data' => $order
        ]);
    }

    /**
     * Remove the specified order.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commande supprimée avec succès'
        ]);
    }
}