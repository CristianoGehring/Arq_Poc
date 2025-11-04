<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API Version 1
Route::prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');

    // Adicione suas rotas v1 aqui
});
