<?php

namespace App\Domains\Assessment\Mail;

use App\Domains\Application\Models\Application;
use App\Domains\Assessment\Models\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
        public readonly Assessment $assessment,
        public readonly string $assessmentUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Undangan Tes Seleksi – {$this->assessment->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assessment-invitation',
            with: [
                'name' => $this->application->personal_info['fullName'] ?? 'Pelamar',
            ],
        );
    }
}
