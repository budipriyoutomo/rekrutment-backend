<?php

namespace App\Domains\ProfileCompletion\Mail;

use App\Domains\Application\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileCompletionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
        public readonly string $token,
        public readonly string $completionUrl,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->application->personal_info['fullName'] ?? 'Pelamar';

        return new Envelope(
            subject: "Lengkapi Profil Lamaran Anda – {$name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-completion',
        );
    }
}
