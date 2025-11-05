<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerController;

Route::prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    Route::apiResource('customers', CustomerController::class);

    // Rotas adicionais
    Route::post('customers/{id}/activate', [CustomerController::class, 'activate']);
    Route::post('customers/{id}/deactivate', [CustomerController::class, 'deactivate']);
    Route::post('customers/{id}/block', [CustomerController::class, 'block']);
});
