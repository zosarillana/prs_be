<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Auth routes (session-based)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    // ====== 🔹 Authentication Routes ======
    Route::post('/change-password', [AuthController::class, 'changePassword']); // 🆕 User self password change
    // ======================================

});

Route::get('/files/signatures/{filename}', function ($filename) {
    $path = storage_path("app/public/signatures/{$filename}");

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
    ]);
});
// Add this debug route
// Route::get('/test-urls', function () {
//     return [
//         'app_url' => config('app.url'),
//         'asset_url' => asset('storage/signatures/5qX1rLPQzvKZL79iRVVOF2s7lUsEGfTkPxkfJn46.png'),
//         'storage_url' => \Storage::url('signatures/test.png'),
//         'url_current' => url()->current(),
//         'request_root' => request()->root(),
//         'request_url' => request()->url(),
//         'http_host' => request()->header('host'),
//         'server_name' => request()->server('SERVER_NAME'),
//     ];
// });

Route::get('/debug-broadcasting', function () {
    return [
        'default_connection' => config('broadcasting.default'),
        'broadcast_connection' => env('BROADCAST_CONNECTION'),
        'broadcast_driver' => env('BROADCAST_DRIVER'),
        'reverb_config' => config('broadcasting.connections.reverb'),
        'all_env' => [
            'REVERB_APP_ID' => env('REVERB_APP_ID'),
            'REVERB_APP_KEY' => env('REVERB_APP_KEY'),
            'REVERB_APP_SECRET' => env('REVERB_APP_SECRET'),
        ],
    ];
});
