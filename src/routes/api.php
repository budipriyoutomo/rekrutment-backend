<?php

use App\Enums\Role;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AssessmentTakeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InterviewerController;
use App\Http\Controllers\JobRequestController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OfferResponseController;
use App\Http\Controllers\OnboardingTemplateController;
use App\Http\Controllers\PreboardingController;
use App\Http\Controllers\PreboardingFormController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\SalarySlipController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacancyController;
use Illuminate\Support\Facades\Route;

// Rute publik untuk profile completion (diakses oleh kandidat via link email)
Route::prefix('profile-completion')->group(function () {
    Route::get('/{token}', [ProfileCompletionController::class, 'validateToken']);
    Route::post('/{token}/complete', [ProfileCompletionController::class, 'complete']);
});

// Rute publik untuk kandidat mengerjakan tes (diakses via link email, token = kredensial)
Route::prefix('assessment')->group(function () {
    Route::get('/{token}', [AssessmentTakeController::class, 'validateToken']);
    Route::post('/{token}/start', [AssessmentTakeController::class, 'start']);
    Route::post('/{token}/submit', [AssessmentTakeController::class, 'submit']);
});

Route::prefix('offer')->group(function () {
    Route::get('/{token}', [OfferResponseController::class, 'show']);
    Route::get('/{token}/pdf', [OfferResponseController::class, 'pdf']);
    Route::post('/{token}/accept', [OfferResponseController::class, 'accept']);
    Route::post('/{token}/decline', [OfferResponseController::class, 'decline']);
});

Route::prefix('preboarding')->group(function () {
    Route::get('/{token}', [PreboardingFormController::class, 'show']);
    Route::post('/{token}/documents', [PreboardingFormController::class, 'upload']);
    Route::post('/{token}/submit', [PreboardingFormController::class, 'submit']);
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
    Route::post('/quick', [ApplicationController::class, 'submitQuick']);
    Route::post('/upload', [ApplicationController::class, 'upload']);
    Route::get('/status/{id}', [ApplicationController::class, 'status']);
});

// Dikonsumsi oleh menu Applicants, Candidates, Pipeline, Documents, dan
// dropdown di halaman Interviews. Boleh diakses bila user punya salah satu izin.
Route::prefix('applicants')->middleware('permission:applicants,candidates,pipeline,documents,interviews')->group(function () {
    Route::get('/', [ApplicationController::class, 'index']);
    Route::post('/', [ApplicationController::class, 'submit']);
    // Bulk action halaman Applicants. Harus di atas rute /{id} agar tidak
    // ditangkap sebagai id.
    Route::patch('/bulk-stage', [ApplicationController::class, 'bulkUpdateStatus']);
    Route::get('/{id}', [ApplicationController::class, 'show']);
    Route::patch('/{id}/stage', [ApplicationController::class, 'updateStatus']);
    Route::patch('/{id}/vacancy', [ApplicationController::class, 'assignVacancy']);
    Route::post('/{id}/reject', [ApplicationController::class, 'reject']);
    Route::get('/{id}/evaluation-summary', [ApplicationController::class, 'evaluationSummary']);
    Route::post('/{id}/notes', [ApplicationController::class, 'addNote']);
    Route::get('/{id}/bundle', [ApplicationController::class, 'bundleDocuments']);
    Route::get('/{id}/bundle/status', [ApplicationController::class, 'bundleStatus']);
    Route::post('/{id}/send-profile-completion', [ApplicationController::class, 'sendProfileCompletion']);
});

// Paket tes + penugasannya ke kandidat (sisi HR).
Route::prefix('assessments')->middleware('permission:assessments')->group(function () {
    Route::get('/', [AssessmentController::class, 'index']);
    Route::post('/', [AssessmentController::class, 'store']);
    Route::get('/{id}', [AssessmentController::class, 'show']);
    Route::patch('/{id}', [AssessmentController::class, 'update']);
    Route::delete('/{id}', [AssessmentController::class, 'destroy']);
    Route::post('/{id}/assign', [AssessmentController::class, 'assign']);
});

// Hasil tes kandidat. Prefix terpisah agar tidak bentrok dengan assessments/{id}.
Route::prefix('assessment-assignments')->middleware('permission:assessments')->group(function () {
    Route::get('/', [AssessmentController::class, 'assignments']);
    Route::get('/{id}', [AssessmentController::class, 'assignmentDetail']);
});

// Daftar offer juga dibaca dari profil kandidat (izin candidates/pipeline);
// perubahan hanya untuk pemegang izin offers.
Route::prefix('offers')->group(function () {
    Route::middleware('permission:offers,candidates,pipeline')->group(function () {
        Route::get('/', [OfferController::class, 'index']);
        Route::get('/{id}', [OfferController::class, 'show']);
        Route::get('/{id}/pdf', [OfferController::class, 'pdf']);
    });

    Route::middleware('permission:offers')->group(function () {
        Route::post('/', [OfferController::class, 'store']);
        Route::patch('/{id}', [OfferController::class, 'update']);
        Route::delete('/{id}', [OfferController::class, 'destroy']);
        Route::post('/{id}/send', [OfferController::class, 'send']);
        Route::post('/{id}/withdraw', [OfferController::class, 'withdraw']);
    });
});

// Status pre-boarding juga tampil di profil kandidat (izin candidates/pipeline);
// verifikasi & dokumen hanya untuk pemegang izin onboarding.
Route::prefix('preboardings')->group(function () {
    Route::get('/', [PreboardingController::class, 'index'])->middleware('permission:onboarding,candidates,pipeline');

    Route::middleware('permission:onboarding')->group(function () {
        Route::post('/', [PreboardingController::class, 'store']);
        Route::get('/{id}', [PreboardingController::class, 'show']);
        Route::post('/{id}/resend', [PreboardingController::class, 'resend']);
        Route::post('/{id}/request-revision', [PreboardingController::class, 'requestRevision']);
        Route::get('/{id}/documents/{type}', [PreboardingController::class, 'downloadDocument']);
        Route::post('/{id}/documents/{type}/review', [PreboardingController::class, 'reviewDocument']);
    });
});

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:onboarding,candidates,pipeline');

    Route::middleware('permission:onboarding')->group(function () {
        Route::post('/', [EmployeeController::class, 'store']);
        Route::get('/{id}', [EmployeeController::class, 'show']);
        Route::patch('/{id}', [EmployeeController::class, 'update']);
        Route::post('/{id}/tasks', [EmployeeController::class, 'addTask']);
    });
});

Route::prefix('onboarding-tasks')->middleware('permission:onboarding')->group(function () {
    Route::patch('/{id}', [EmployeeController::class, 'updateTask']);
    Route::delete('/{id}', [EmployeeController::class, 'deleteTask']);
});

Route::prefix('onboarding-templates')->middleware('permission:onboarding')->group(function () {
    Route::get('/', [OnboardingTemplateController::class, 'index']);
    Route::post('/', [OnboardingTemplateController::class, 'store']);
    Route::patch('/{id}', [OnboardingTemplateController::class, 'update']);
    Route::delete('/{id}', [OnboardingTemplateController::class, 'destroy']);
});

Route::prefix('interviews')->middleware('permission:interviews')->group(function () {
    Route::get('/', [InterviewController::class, 'index']);
    Route::post('/', [InterviewController::class, 'store']);
    Route::get('/{id}', [InterviewController::class, 'show']);
    Route::patch('/{id}', [InterviewController::class, 'update']);
    Route::delete('/{id}', [InterviewController::class, 'destroy']);
    Route::post('/{id}/send-invitation', [InterviewController::class, 'sendInvitation']);
});

Route::prefix('interviewers')->middleware('permission:interviewers')->group(function () {
    Route::get('/', [InterviewerController::class, 'index']);
    Route::post('/', [InterviewerController::class, 'store']);
    Route::get('/{id}', [InterviewerController::class, 'show']);
    Route::patch('/{id}', [InterviewerController::class, 'update']);
    Route::delete('/{id}', [InterviewerController::class, 'destroy']);
});

Route::prefix('evaluations')->middleware('permission:evaluations')->group(function () {
    Route::get('/', [EvaluationController::class, 'index']);
    Route::post('/', [EvaluationController::class, 'store']);
    Route::get('/{id}', [EvaluationController::class, 'show']);
    Route::patch('/{id}', [EvaluationController::class, 'update']);
    Route::delete('/{id}', [EvaluationController::class, 'destroy']);
});

// GET master-data terbuka: dipakai untuk dropdown di halaman publik (Quick Apply)
// dan sidebar. Operasi tulis dibatasi izin menu Master Data.
Route::prefix('master-data')->group(function () {
    Route::get('/types', [MasterDataController::class, 'types']);
    Route::get('/', [MasterDataController::class, 'index']);

    Route::middleware('permission:master-data')->group(function () {
        Route::post('/', [MasterDataController::class, 'store']);
        Route::put('/{id}', [MasterDataController::class, 'update']);
        Route::delete('/{id}', [MasterDataController::class, 'destroy']);
        Route::patch('/{id}/toggle', [MasterDataController::class, 'toggleActive']);
    });
});

// GET company terbuka (dipakai lintas halaman); update dibatasi izin Settings.
Route::prefix('settings')->group(function () {
    Route::get('/company', [CompanySettingController::class, 'index']);
    Route::put('/company', [CompanySettingController::class, 'update'])->middleware('permission:settings');
});

// Manajemen user hanya untuk yang punya izin menu Settings (admin/super_admin bypass).
Route::prefix('users')->middleware('permission:settings')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

Route::prefix('job-requests')->middleware('permission:job-requests')->group(function () {
    Route::get('/', [JobRequestController::class, 'index']);
    Route::post('/', [JobRequestController::class, 'store']);
    Route::get('/{id}', [JobRequestController::class, 'show']);
    Route::put('/{id}', [JobRequestController::class, 'update']);
    Route::patch('/{id}', [JobRequestController::class, 'update']);
    Route::delete('/{id}', [JobRequestController::class, 'destroy']);
    Route::post('/{id}/approve', [JobRequestController::class, 'approve']);
    Route::post('/{id}/reject', [JobRequestController::class, 'reject']);
    Route::post('/{id}/create-vacancy', [JobRequestController::class, 'createVacancy'])->middleware('permission:vacancies');
});

Route::get('analytics', [AnalyticsController::class, 'index']);

// Akun email pengirim. Pembacaan (dropdown) terbuka, tetapi pengelolaan
// kredensial dibatasi role admin/super_admin.
Route::prefix('mail-accounts')->group(function () {
    Route::get('/', [MailAccountController::class, 'index']);
    Route::get('/{id}', [MailAccountController::class, 'show']);

    Route::middleware('role:'.Role::ADMIN->value)->group(function () {
        Route::post('/', [MailAccountController::class, 'store']);
        Route::put('/{id}', [MailAccountController::class, 'update']);
        Route::delete('/{id}', [MailAccountController::class, 'destroy']);
    });
});

Route::prefix('salary-slips')->group(function () {
    // Baca & kirim: boleh diakses izin menu "Salary Slips" ATAU "Report Salary Slip".
    // Menu Report menampilkan slip yang sudah terkirim dan bisa melakukan resend.
    Route::middleware('permission:salary-slips,salary-slip-reports')->group(function () {
        Route::get('/template', [SalarySlipController::class, 'template']);
        Route::get('/{id}/pdf', [SalarySlipController::class, 'pdf']);
        Route::get('/', [SalarySlipController::class, 'index']);
        Route::post('/send', [SalarySlipController::class, 'send']);
    });

    // Tulis (import, buat, hapus massal): hanya izin menu "Salary Slips".
    Route::middleware('permission:salary-slips')->group(function () {
        Route::post('/', [SalarySlipController::class, 'store']);
        Route::post('/import', [SalarySlipController::class, 'import']);
        Route::delete('/bulk', [SalarySlipController::class, 'bulkDelete']);
    });
});

// GET vacancies terbuka: dipakai halaman publik (landing page daftar lowongan).
// Operasi tulis dibatasi izin menu Vacancies.
Route::prefix('vacancies')->group(function () {
    Route::get('/', [VacancyController::class, 'index']);
    Route::get('/{id}', [VacancyController::class, 'show']);

    Route::middleware('permission:vacancies')->group(function () {
        Route::post('/', [VacancyController::class, 'store']);
        Route::put('/{id}', [VacancyController::class, 'update']);
        Route::patch('/{id}', [VacancyController::class, 'update']);
        Route::patch('/{id}/close', [VacancyController::class, 'close']);
        Route::delete('/{id}', [VacancyController::class, 'destroy']);
    });
});
