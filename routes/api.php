<?php

use App\Http\Controllers\PurchaseReportController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('purchase-reports', PurchaseReportController::class);
// // New table endpoint
// Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('purchase-reports', PurchaseReportController::class);
    // New table endpoint
    Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);
});