<?php

namespace App\Domains\Employee\Mail;

use App\Domains\Employee\Models\Employee;
use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email sambutan H-1 sebelum hari pertama kerja. */
class WelcomeEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Employee $employee) {}

    public function envelope(): Envelope
    {
        $company = CompanySetting::get('company_name') ?: config('app.name');

        return new Envelope(subject: "Selamat Datang di {$company} – Besok Hari Pertama Anda");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-employee',
            with: [
                'companyName'  => CompanySetting::get('company_name') ?: config('app.name'),
                'hrEmail'      => CompanySetting::get('company_email'),
                'hrPhone'      => CompanySetting::get('company_phone'),
                'startDate'    => $this->employee->start_date->locale('id')->translatedFormat('l, j F Y'),
            ],
        );
    }
}
