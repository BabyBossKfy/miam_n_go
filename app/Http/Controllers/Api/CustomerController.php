<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'Liste des clients récupérée avec succès',
            'data' => Customer::with('orders')->get()
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name_customers' => 'required|string|max:250',
            'last_name_customers' => 'required|string|max:250',
            'phone_customers' => 'required|string|max:250',
            'mail_customers' => 'nullable|email|max:250',
        ]);

        $customer = Customer::create([
            'first_name_customers' => $request->first_name_customers,
            'last_name_customers' => $request->last_name_customers,
            'phone_customers' => $request->phone_customers,
            'mail_customers' => $request->mail_customers,
            'status' => $request->status ?? 'ACTIVE',
            'created_by' => $request->created_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client créé avec succès',
            'data' => $customer
        ], 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id)
    {
        $customer = Customer::with('orders')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Client récupéré avec succès',
            'data' => $customer
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $id)
    {
        $customer = Customer::findOrFail($id);

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
            'status' => $request->status ?? $customer->status,
            'updated_by' => $request->updated_by ?? 'SYSTEM',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client mis à jour avec succès',
            'data' => $customer
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);

        $customer->update([
            'deleted_by' => 'SYSTEM',
        ]);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé avec succès'
        ]);
    }
}