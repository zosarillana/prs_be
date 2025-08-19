<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API routes
Route::prefix('api')->group(function () {
    Route::get('/test', function () {
        return response()->json(['message' => 'Hello from Laravel 12 API!']);
    });
});
