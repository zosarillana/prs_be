<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Auth routes (session-based)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
});

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
        ]
    ];
});
