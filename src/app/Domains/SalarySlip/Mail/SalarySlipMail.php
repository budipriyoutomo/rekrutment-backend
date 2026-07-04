<?php

namespace App\Domains\SalarySlip\Mail;

use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SalarySlip $salarySlip,
        public readonly ?string $pdfPath = null,
        public readonly ?MailAccount $mailAccount = null,
    ) {}

    public function envelope(): Envelope
    {
        // From memakai identitas akun terpilih; jika tidak ada, pakai default global.
        $from = $this->mailAccount
            ? new Address($this->mailAccount->from_email, $this->mailAccount->from_name)
            : null;

        return new Envelope(
            from: $from,
            subject: "Slip Gaji {$this->salarySlip->periode} – {$this->salarySlip->nama}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.salary-slip',
        );
    }

    /**
     * @return Attachment[]
     */
    public function attachments(): array
    {
        if (! $this->pdfPath) {
            return [];
        }

        return [
            Attachment::fromPath($this->pdfPath)
                ->as("Slip_Gaji_{$this->salarySlip->periode}_{$this->salarySlip->nik}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
