<?php

namespace App\Domains\Preboarding\Mail;

use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Undangan mengisi data karyawan baru. Dipakai juga untuk permintaan revisi
 * ($revision = true) dengan daftar dokumen yang perlu diunggah ulang.
 */
class PreboardingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Preboarding $preboarding,
        public readonly bool $revision = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->revision
                ? 'Perbaikan Data Karyawan Baru Diperlukan'
                : 'Selamat Bergabung – Lengkapi Data Karyawan Baru',
        );
    }

    public function content(): Content
    {
        $rejected = collect($this->preboarding->rejectedDocuments())
            ->map(fn (string $type) => [
                'label' => Preboarding::REQUIRED_DOCUMENTS[$type] ?? $type,
                'note' => $this->preboarding->document($type)['note'] ?? null,
            ])
            ->all();

        return new Content(
            view: 'emails.preboarding-invitation',
            with: [
                'name' => $this->preboarding->candidateName(),
                'companyName' => CompanySetting::get('company_name') ?: config('app.name'),
                'position' => $this->preboarding->position(),
                'url' => $this->preboarding->publicUrl(),
                'expiresAt' => $this->preboarding->token_expires_at->locale('id')->translatedFormat('j F Y'),
                'documents' => array_values(Preboarding::REQUIRED_DOCUMENTS),
                'rejected' => $rejected,
            ],
        );
    }
}
