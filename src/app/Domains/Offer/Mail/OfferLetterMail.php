<?php

namespace App\Domains\Offer\Mail;

use App\Domains\Offer\Models\Offer;
use App\Domains\Offer\Services\OfferLetterPdf;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Offer $offer) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Surat Penawaran Kerja – {$this->offer->position}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offer-letter',
            with: [
                'name' => $this->offer->candidateName(),
                'companyName' => CompanySetting::get('company_name') ?: config('app.name'),
                'offerUrl' => $this->offer->publicUrl(),
            ],
        );
    }

    /**
     * PDF dirender saat email benar-benar dibangun (di queue worker), jadi tidak
     * ada file sementara yang harus bertahan sampai antrean diproses.
     *
     * @return Attachment[]
     */
    public function attachments(): array
    {
        $offer = $this->offer;

        return [
            Attachment::fromData(fn () => app(OfferLetterPdf::class)->render($offer), $offer->pdfFileName())
                ->withMime('application/pdf'),
        ];
    }
}
