<?php

namespace App\Domains\Interview\Actions;

use App\Domains\Interview\Mail\InterviewInvitationMail;
use App\Domains\Interview\Models\Interview;
use App\Domains\Interviewer\Models\Interviewer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInterviewInvitationAction
{
    /**
     * Mengirim email undangan interview ke kandidat dan semua interviewer.
     * Bersifat best-effort per penerima: kegagalan satu email dicatat di log
     * dan tidak menghentikan pengiriman ke penerima lain.
     *
     * @return bool true jika minimal satu email berhasil terkirim.
     */
    public function execute(Interview $interview): bool
    {
        $interview->loadMissing('applicant');
        $sentAny = false;

        // 1. Email ke kandidat
        $candidateEmail = $interview->applicant?->contact_info['email'] ?? null;
        if (!empty($candidateEmail)) {
            $sentAny = $this->send(
                $candidateEmail,
                new InterviewInvitationMail($interview, $interview->applicant_name ?: 'Kandidat', 'candidate'),
                ['interview_id' => $interview->id, 'audience' => 'candidate']
            ) || $sentAny;
        } else {
            Log::warning('Interview invitation: kandidat tidak memiliki email', [
                'interview_id' => $interview->id,
            ]);
        }

        // 2. Email ke tiap interviewer (lookup email dari master data by nama)
        $names = array_filter((array) ($interview->interviewers ?? []));
        if (!empty($names)) {
            $interviewers = Interviewer::whereIn('name', $names)->get();

            foreach ($interviewers as $interviewer) {
                if (empty($interviewer->email)) {
                    Log::warning('Interview invitation: interviewer tanpa email', [
                        'interview_id' => $interview->id,
                        'interviewer' => $interviewer->name,
                    ]);
                    continue;
                }

                $sentAny = $this->send(
                    $interviewer->email,
                    new InterviewInvitationMail($interview, $interviewer->name, 'interviewer'),
                    ['interview_id' => $interview->id, 'audience' => 'interviewer', 'interviewer' => $interviewer->name]
                ) || $sentAny;
            }
        }

        return $sentAny;
    }

    private function send(string $email, InterviewInvitationMail $mail, array $context): bool
    {
        try {
            Mail::to($email)->send($mail);
            return true;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim email undangan interview: ' . $e->getMessage(), $context + [
                'email' => $email,
            ]);
            return false;
        }
    }
}
