<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name_customers' => 'required|string|max:250',
            'last_name_customers' => 'required|string|max:250',
            'phone_customers' => 'required|string|max:250',
            'mail_customers' => 'required|email|max:250|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->first_name_customers . ' ' . $request->last_name_customers,
                'email' => $request->mail_customers,
                'password' => Hash::make($request->password),
            ]);

            $customer = Customer::create([
                'id_users' => $user->id,
                'first_name_customers' => $request->first_name_customers,
                'last_name_customers' => $request->last_name_customers,
                'phone_customers' => $request->phone_customers,
                'mail_customers' => $request->mail_customers,
                'status' => 'ACTIVE',
                'created_by' => 'SYSTEM',
            ]);

            $token = $user->createToken('miam-n-go-token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'token_type' => 'Bearer',
                'token' => $token,
                'user' => $user,
                'customer' => $customer
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l’inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'mail_customers' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->mail_customers)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'mail_customers' => ['Les identifiants sont incorrects.'],
            ]);
        }

        $token = $user->createToken('miam-n-go-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $user,
            'customer' => $user->customer
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Utilisateur connecté récupéré avec succès',
            'user' => $request->user(),
            'customer' => $request->user()->customer
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }
}