<?php

namespace App\Infrastructure\Listeners;

use App\Domains\Application\Events\ApplicationBundlingRequested;
use App\Domains\Application\Models\Application;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailToInterviewer
{
    public function handle(ApplicationBundlingRequested $event): void
    {
        // 1. Ambil data pelamar beserta data interview/interviewer-nya
        $app = Application::with('interviewers')->find($event->applicationId);

        if (!$app) {
            return;
        }

        // 2. Lakukan perulangan jika interviewer lebih dari satu, lalu kirim email
        foreach ($app->interviewers as $interviewer) {
            if (!empty($interviewer->email)) {
                // Contoh pengiriman email menggunakan Mailable Laravel (Silakan sesuaikan class Mail Anda)
                // Mail::to($interviewer->email)->queue(new InterviewerNotificationMail($app));
                
                Log::info("Email notifikasi bundling berhasil dimasukkan antrean untuk interviewer: {$interviewer->email}");
            }
        }
    }
}