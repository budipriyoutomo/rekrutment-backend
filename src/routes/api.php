<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\InterviewController;

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

Route::prefix('applicants')->group(function () {
    Route::get('/', [ApplicationController::class, 'index']);
    Route::post('/', [ApplicationController::class, 'submit']);
    Route::get('/{id}', [ApplicationController::class, 'show']);
    Route::patch('/{id}/stage', [ApplicationController::class, 'updateStatus']);
});

Route::prefix('interviews')->group(function () {
    Route::get('/', [InterviewController::class, 'index']);
    Route::post('/', [InterviewController::class, 'store']);
    Route::get('/{id}', [InterviewController::class, 'show']);
    Route::patch('/{id}', [InterviewController::class, 'update']);
    Route::delete('/{id}', [InterviewController::class, 'destroy']);
    Route::post('/{id}/send-invitation', [InterviewController::class, 'sendInvitation']);
});
