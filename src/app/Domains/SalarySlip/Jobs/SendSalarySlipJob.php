<?php

namespace App\Domains\SalarySlip\Jobs;

use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSalarySlipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly string $salarySlipId) {}

    public function handle(): void
    {
        $slip = SalarySlip::find($this->salarySlipId);

        if (!$slip) return;

        Mail::to($slip->email)->send(new SalarySlipMail($slip));

        $slip->update(['sent_at' => now()]);
    }
}
