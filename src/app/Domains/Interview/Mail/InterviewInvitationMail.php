<?php

namespace App\Domains\Interview\Mail;

use App\Domains\Interview\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Interview $interview   Jadwal interview terkait
     * @param string    $recipientName Nama penerima (kandidat / interviewer)
     * @param string    $audience    'candidate' atau 'interviewer'
     */
    public function __construct(
        public readonly Interview $interview,
        public readonly string $recipientName,
        public readonly string $audience = 'candidate',
    ) {}

    public function envelope(): Envelope
    {
        $position = $this->interview->position ?: 'Interview';

        $subject = $this->audience === 'interviewer'
            ? "Penugasan Interview – {$this->interview->applicant_name} ({$position})"
            : "Undangan Interview – {$position}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.interview-invitation',
            with: [
                'interview' => $this->interview,
                'recipientName' => $this->recipientName,
                'audience' => $this->audience,
            ],
        );
    }
}
