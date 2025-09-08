<?php

use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route::apiResource('purchase-reports', PurchaseReportController::class);
// // New table endpoint
// Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);

Route::middleware('auth:sanctum')->group(function () {
    // 👇 custom routes must be declared before apiResource
    Route::get('purchase-reports/summary', [PurchaseReportController::class, 'summaryCounts']);
    Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);
    Route::patch('purchase-reports/{id}/update-item-status-only', [PurchaseReportController::class, 'updateItemStatusOnly']);
    Route::patch('purchase-reports/{id}/po-no', [PurchaseReportController::class, 'updatePoNo']);
    Route::patch('purchase-reports/{id}/approve-item', [PurchaseReportController::class, 'approveItem']);

    // Resource route last
    Route::apiResource('purchase-reports', PurchaseReportController::class)
        ->parameters(['purchase-reports' => 'id']);

    Route::apiResource('users', UserController::class);
    Route::post('users/{id}/signature', [UserController::class, 'updateSignature']);
});
