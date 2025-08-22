<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PurchaseReportController; // ✅ import your controller

Route::get('/test', function () {
    return response()->json(['message' => 'Hello from Laravel 12 API!']);
});

// 🔹 Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// 🔹 Purchase Reports CRUD (protected by auth)
Route::apiResource('purchase-reports', PurchaseReportController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);


});
