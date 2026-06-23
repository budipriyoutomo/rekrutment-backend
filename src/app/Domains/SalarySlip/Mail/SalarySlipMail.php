<?php

namespace App\Domains\SalarySlip\Mail;

use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SalarySlip $salarySlip) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Slip Gaji {$this->salarySlip->periode} – {$this->salarySlip->nama}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.salary-slip',
        );
    }
}
