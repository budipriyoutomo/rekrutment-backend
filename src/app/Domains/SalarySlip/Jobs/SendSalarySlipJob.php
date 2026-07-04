<?php

namespace App\Domains\SalarySlip\Jobs;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Mail\SalarySlipMail;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use App\Domains\SalarySlip\Services\MailAccountResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Kirim 1 slip gaji: generate PDF -> attach -> kirim email, sambil menjaga
 * lifecycle status (queued -> processing -> sent/failed) di salary_slips dan
 * histori per-pengiriman di send_jobs.
 *
 * Idempotent: slip yang sudah `sent` tidak dikirim ulang kecuali $resend = true.
 * Retry: sampai 3x dengan backoff bertingkat untuk kegagalan transient (SMTP dsb).
 */
class SendSalarySlipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Backoff bertingkat (detik) antar percobaan. */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $salarySlipId,
        public readonly string $sendJobId,
        public readonly bool $resend = false,
    ) {}

    public function handle(GeneratePayslipPdfAction $pdfAction, MailAccountResolver $resolver): void
    {
        $slip = SalarySlip::find($this->salarySlipId);
        if (! $slip) {
            return;
        }

        $sendJob = SendJob::find($this->sendJobId);

        // Idempotency: sudah terkirim & bukan permintaan resend -> no-op.
        if ($slip->send_status === SalarySlip::STATUS_SENT && ! $this->resend) {
            $sendJob?->update([
                'status'      => SendJob::STATUS_SUCCESS,
                'finished_at' => now(),
            ]);

            return;
        }

        $slip->update(['send_status' => SalarySlip::STATUS_PROCESSING]);
        $sendJob?->update([
            'status'     => SendJob::STATUS_PROCESSING,
            'attempt'    => (int) ($sendJob->attempt ?? 0) + 1,
            'started_at' => $sendJob->started_at ?? now(),
        ]);

        // Generate PDF (idempotent) lalu kirim sebagai attachment.
        $pdfPath = $pdfAction->execute($slip);

        // Pilih akun email pengirim jika ditentukan (transport SMTP dinamis).
        $account = $slip->mail_account_id
            ? MailAccount::where('is_active', true)->find($slip->mail_account_id)
            : null;

        $mailable = new SalarySlipMail($slip, $pdfPath, $account);

        if ($account && $account->driver === 'smtp') {
            Mail::mailer($resolver->build($account))->to($slip->email)->send($mailable);
        } else {
            Mail::to($slip->email)->send($mailable);
        }

        $slip->update([
            'send_status' => SalarySlip::STATUS_SENT,
            'sent_at'     => now(),
            'send_error'  => null,
        ]);
        $sendJob?->update([
            'status'      => SendJob::STATUS_SUCCESS,
            'finished_at' => now(),
        ]);
    }

    /**
     * Dipanggil saat seluruh retry habis (kegagalan permanen).
     */
    public function failed(\Throwable $e): void
    {
        SalarySlip::whereKey($this->salarySlipId)->update([
            'send_status' => SalarySlip::STATUS_FAILED,
            'send_error'  => $e->getMessage(),
        ]);

        SendJob::whereKey($this->sendJobId)->update([
            'status'        => SendJob::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'finished_at'   => now(),
        ]);
    }
}
