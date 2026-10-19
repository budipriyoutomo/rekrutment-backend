<?php

use App\Domains\Employee\Services\EmployeeService;
use App\Domains\Offer\Services\OfferService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('offers:expire', function (OfferService $service) {
    $count = $service->expireOverdue();
    $this->info("{$count} offer ditandai expired.");
})->purpose('Tandai offer terkirim yang lewat masa berlaku sebagai expired');

// Dijalankan oleh `schedule:work` di Supervisor
Schedule::command('offers:expire')->dailyAt('00:10');

Artisan::command('onboarding:welcome', function (EmployeeService $service) {
    $count = $service->sendWelcomeEmails();
    $this->info("{$count} email sambutan dikirim.");
})->purpose('Kirim email sambutan ke karyawan baru yang mulai bekerja besok');

Schedule::command('onboarding:welcome')->dailyAt('08:00');
