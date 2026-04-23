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



Route::prefix('applicants')->group(function () {
    Route::post('/', [ApplicationController::class, 'submit']);
    Route::get('/{id}', [ApplicationController::class, 'show']);
});


    Route::get('/applicants', [ApplicationController::class, 'index']);

    use Illuminate\Support\Facades\Storage;

Route::get('/test-s3', function () {
    $result = Storage::disk('s3')->put('test.txt', 'hello world');

    return [
        'result' => $result,
        'exists' => Storage::disk('s3')->exists('test.txt'),
    ];
});