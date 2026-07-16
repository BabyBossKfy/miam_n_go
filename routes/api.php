<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
| Accessibles sans token.
| Utilisées par les visiteurs, les clients non connectés et l'application mobile.
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::apiResource('categories', CategoryController::class)->only([
    'index',
    'show'
]);

Route::apiResource('partners', PartnerController::class)->only([
    'index',
    'show'
]);

Route::apiResource('products', ProductController::class)->only([
    'index',
    'show'
]);

/*
|--------------------------------------------------------------------------
| Routes protégées
|--------------------------------------------------------------------------
| Nécessitent un token Sanctum.
| Header obligatoire :
| Authorization: Bearer TOKEN
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authenticated User
    |--------------------------------------------------------------------------
    */

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Products sécurisés
    |--------------------------------------------------------------------------
    | ADMIN    : peut tout gérer
    | PARTNER  : peut gérer uniquement ses produits
    | CUSTOMER : accès refusé à la création/modification/suppression
    |--------------------------------------------------------------------------
    */

    Route::get('/my-products', [ProductController::class, 'myProducts']);

    Route::apiResource('products', ProductController::class)->except([
        'index',
        'show'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Routes métier sécurisées
    |--------------------------------------------------------------------------
    */

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('deliveries', DeliveryController::class);
});

/*
|--------------------------------------------------------------------------
| User route Laravel par défaut
|--------------------------------------------------------------------------
| Tu peux la garder pour test, mais elle n'est pas obligatoire.
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');