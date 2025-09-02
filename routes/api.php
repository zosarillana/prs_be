<?php

use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('purchase-reports', PurchaseReportController::class);
// // New table endpoint
// Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('purchase-reports', PurchaseReportController::class);
    Route::apiResource('users', UserController::class);
    Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);
    Route::patch('purchase-reports/{id}/approve-item', [PurchaseReportController::class, 'approveItem']); 
});