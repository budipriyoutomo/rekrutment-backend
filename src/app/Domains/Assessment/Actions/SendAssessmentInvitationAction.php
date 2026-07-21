<?php

namespace App\Domains\Assessment\Actions;

use App\Domains\Application\Models\Application;
use App\Domains\Assessment\Mail\AssessmentInvitationMail;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentAssignment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendAssessmentInvitationAction
{
    public function execute(
        Application $application,
        Assessment $assessment,
        int $validDays = 7,
    ): AssessmentAssignment {
        $email = $application->contact_info['email'] ?? null;

        if (empty($email)) {
            throw new \InvalidArgumentException('Pelamar tidak memiliki alamat email yang valid.');
        }

        // Undangan lama yang belum dikerjakan dibatalkan agar kandidat tidak
        // memegang dua token aktif untuk paket yang sama.
        AssessmentAssignment::where('application_id', $application->id)
            ->where('assessment_id', $assessment->id)
            ->where('status', '!=', 'graded')
            ->delete();

        // Soal dibekukan saat penugasan: kandidat mengerjakan dan dinilai atas
        // versi soal yang ini, sehingga HR mengedit paket tidak mengubah tes
        // yang sedang berjalan maupun hasil yang sudah tersimpan.
        $assignment = AssessmentAssignment::create([
            'application_id'     => $application->id,
            'assessment_id'      => $assessment->id,
            'token'              => Str::random(64),
            'status'             => 'sent',
            'expires_at'         => now()->addDays($validDays),
            'questions_snapshot' => $assessment->loadMissing('questions')->toQuestionsSnapshot(),
        ]);

        $url = rtrim(config('app.frontend_url'), '/') . '/assessment/' . $assignment->token;

        Mail::to($email)->send(new AssessmentInvitationMail($application, $assessment, $url));

        return $assignment;
    }
}
