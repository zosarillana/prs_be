<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ModulesController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\UserPriviligesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\AuditLogsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseReportController;
use App\Http\Controllers\PurchaseReportProgressController;
use App\Http\Controllers\UomController;
use App\Http\Controllers\UserController;

Route::withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
])->middleware('auth:sanctum')->group(function () {

    // ====== 🔹 New Logs Endpoints ======
    Route::get('user-logs', [UserLogsController::class, 'index']);
    Route::get('audit-logs', [AuditLogsController::class, 'index']);
    // ===================================

    Route::get('purchase-reports/summary', [PurchaseReportController::class, 'summaryCounts']);
    Route::get('purchase-reports-table', [PurchaseReportController::class, 'table']);
    Route::get('/purchase-reports/table-reports', [PurchaseReportController::class, 'tableReports']);
    Route::get('/purchase-reports/next-series', [PurchaseReportController::class, 'getNextSeriesNo']);

    Route::patch('purchase-reports/{id}/update-item-status-only', [PurchaseReportController::class, 'updateItemStatusOnly']);
    Route::patch('purchase-reports/{id}/po-no', [PurchaseReportController::class, 'updatePoNo']);
    Route::patch('purchase-reports/{id}/cancel-po-no', [PurchaseReportController::class, 'cancelPoNo']);
    Route::patch('purchase-reports/{id}/return-po-no', [PurchaseReportController::class, 'returnPoNo']);
    Route::patch('purchase-reports/{id}/approve-item', [PurchaseReportController::class, 'approveItem']);
    Route::post('/purchase-reports/{id}/po-approve-date', [PurchaseReportController::class, 'poApproveDate']);
    Route::patch('/purchase-reports/{id}/delivery-status', [PurchaseReportController::class, 'updateDeliveryStatus']);

    Route::prefix('purchase-reports')->group(function () {
        Route::get('{id}/progresses', [PurchaseReportProgressController::class, 'index']);
        Route::post('{id}/progresses', [PurchaseReportProgressController::class, 'store']);
        Route::put('progresses/{id}', [PurchaseReportProgressController::class, 'update']);
        Route::delete('progresses/{id}', [PurchaseReportProgressController::class, 'destroy']);
    });

    Route::apiResource('modules', ModulesController::class);
    Route::apiResource('user-privileges', UserPriviligesController::class);
    Route::apiResource('departments', DepartmentController::class);
    Route::apiResource('tags', TagsController::class);

    Route::apiResource('purchase-reports', PurchaseReportController::class)
        ->parameters(['purchase-reports' => 'id']);

    Route::apiResource('uoms', UomController::class)
        ->parameters(['uoms' => 'id']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/counts', [NotificationController::class, 'counts']);
    Route::get('/notifications/summary', [NotificationController::class, 'summary']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::post('users/{id}/signature', [UserController::class, 'updateSignature']);
    Route::put('users/{id}/password', [UserController::class, 'updatePassword']);

    Route::apiResource('users', UserController::class);
});
