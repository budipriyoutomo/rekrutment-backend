<?php

namespace App\Domains\Application\Mail;

use App\Domains\Application\Models\Application;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan penolakan ke kandidat. Alasan penolakan sengaja TIDAK disertakan —
 * itu catatan internal HR.
 */
class ApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Informasi Hasil Seleksi – ' . $this->position(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-rejected',
            with: [
                'name'        => $this->application->personal_info['fullName'] ?? 'Pelamar',
                'position'    => $this->position(),
                'companyName' => CompanySetting::get('company_name') ?: config('app.name'),
            ],
        );
    }

    private function position(): string
    {
        return $this->application->vacancy?->title
            ?? $this->application->additional_info['positionApplied']
            ?? 'Lamaran Anda';
    }
}
