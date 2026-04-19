<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationController;



Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

Route::prefix('applications')->group(function () {
    Route::post('/', [ApplicationController::class, 'submit']);
    Route::post('/upload', [ApplicationController::class, 'upload']);
    Route::get('/status/{id}', [ApplicationController::class, 'status']);
});