<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InterviewerController;
use App\Http\Controllers\JobRequestController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\VacancyController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\UserController;

// Rute publik untuk profile completion (diakses oleh kandidat via link email)
Route::prefix('profile-completion')->group(function () {
    Route::get('/{token}', [ProfileCompletionController::class, 'validateToken']);
    Route::post('/{token}/complete', [ProfileCompletionController::class, 'complete']);
});

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);

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
    Route::post('/{id}/notes', [ApplicationController::class, 'addNote']);
    Route::get('/{id}/bundle', [ApplicationController::class, 'bundleDocuments']);
    Route::get('/{id}/bundle/status', [ApplicationController::class, 'bundleStatus']);
    Route::post('/{id}/send-profile-completion', [ApplicationController::class, 'sendProfileCompletion']);
});

Route::prefix('interviews')->group(function () {
    Route::get('/', [InterviewController::class, 'index']);
    Route::post('/', [InterviewController::class, 'store']);
    Route::get('/{id}', [InterviewController::class, 'show']);
    Route::patch('/{id}', [InterviewController::class, 'update']);
    Route::delete('/{id}', [InterviewController::class, 'destroy']);
    Route::post('/{id}/send-invitation', [InterviewController::class, 'sendInvitation']);
});

Route::prefix('interviewers')->group(function () {
    Route::get('/', [InterviewerController::class, 'index']);
    Route::post('/', [InterviewerController::class, 'store']);
    Route::get('/{id}', [InterviewerController::class, 'show']);
    Route::patch('/{id}', [InterviewerController::class, 'update']);
    Route::delete('/{id}', [InterviewerController::class, 'destroy']);
});

Route::prefix('evaluations')->group(function () {
    Route::get('/', [EvaluationController::class, 'index']);
    Route::post('/', [EvaluationController::class, 'store']);
    Route::get('/{id}', [EvaluationController::class, 'show']);
    Route::patch('/{id}', [EvaluationController::class, 'update']);
    Route::delete('/{id}', [EvaluationController::class, 'destroy']);
});

Route::prefix('master-data')->group(function () {
    Route::get('/types', [MasterDataController::class, 'types']);
    Route::get('/', [MasterDataController::class, 'index']);
    Route::post('/', [MasterDataController::class, 'store']);
    Route::put('/{id}', [MasterDataController::class, 'update']);
    Route::delete('/{id}', [MasterDataController::class, 'destroy']);
    Route::patch('/{id}/toggle', [MasterDataController::class, 'toggleActive']);
});

Route::prefix('settings')->group(function () {
    Route::get('/company', [CompanySettingController::class, 'index']);
    Route::put('/company', [CompanySettingController::class, 'update']);
});

Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

Route::prefix('job-requests')->group(function () {
    Route::get('/', [JobRequestController::class, 'index']);
    Route::post('/', [JobRequestController::class, 'store']);
    Route::get('/{id}', [JobRequestController::class, 'show']);
    Route::put('/{id}', [JobRequestController::class, 'update']);
    Route::patch('/{id}', [JobRequestController::class, 'update']);
    Route::delete('/{id}', [JobRequestController::class, 'destroy']);
    Route::post('/{id}/approve', [JobRequestController::class, 'approve']);
    Route::post('/{id}/reject', [JobRequestController::class, 'reject']);
});

Route::prefix('vacancies')->group(function () {
    Route::get('/', [VacancyController::class, 'index']);
    Route::post('/', [VacancyController::class, 'store']);
    Route::get('/{id}', [VacancyController::class, 'show']);
    Route::put('/{id}', [VacancyController::class, 'update']);
    Route::patch('/{id}', [VacancyController::class, 'update']);
    Route::patch('/{id}/close', [VacancyController::class, 'close']);
    Route::delete('/{id}', [VacancyController::class, 'destroy']);
});
