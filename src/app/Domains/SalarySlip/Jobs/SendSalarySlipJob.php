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
use RuntimeException;

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

    /**
     * Konversi PDF payslip via LibreOffice dibatasi 120 dtk; harus < worker
     * --timeout (180) dan < DB_QUEUE_RETRY_AFTER (240) agar tidak dijalankan ganda.
     */
    public int $timeout = 170;

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
                'status' => SendJob::STATUS_SUCCESS,
                'finished_at' => now(),
            ]);

            return;
        }

        $slip->update(['send_status' => SalarySlip::STATUS_PROCESSING]);
        $sendJob?->update([
            'status' => SendJob::STATUS_PROCESSING,
            'attempt' => (int) ($sendJob->attempt ?? 0) + 1,
            'started_at' => $sendJob->started_at ?? now(),
        ]);

        // Akun pengirim wajib akun Slip Gaji yang aktif — tidak pernah jatuh
        // diam-diam ke mailer .env (email rekrutmen).
        $account = $this->resolveAccount($slip);
        if (is_string($account)) {
            $this->failWithoutRetry(new RuntimeException($account));

            return;
        }

        // Generate PDF (idempotent) lalu kirim sebagai attachment.
        $pdfPath = $pdfAction->execute($slip);

        $mailable = new SalarySlipMail($slip, $pdfPath, $account);
        Mail::mailer($resolver->build($account))->to($slip->email)->send($mailable);

        $slip->update([
            'send_status' => SalarySlip::STATUS_SENT,
            'sent_at' => now(),
            'send_error' => null,
        ]);
        $sendJob?->update([
            'status' => SendJob::STATUS_SUCCESS,
            'finished_at' => now(),
        ]);
    }

    /**
     * Akun yang dipilih saat kirim, atau akun default purpose salary_slip.
     *
     * @return MailAccount|string akun, atau pesan error bila tidak bisa dipakai
     */
    private function resolveAccount(SalarySlip $slip): MailAccount|string
    {
        $query = MailAccount::query()
            ->where('is_active', true)
            ->where('purpose', MailAccount::PURPOSE_SALARY_SLIP);

        $account = $slip->mail_account_id
            ? $query->find($slip->mail_account_id)
            : $query->where('is_default', true)->first();

        if (! $account) {
            return $slip->mail_account_id
                ? 'Akun email pengirim tidak aktif atau sudah dihapus. Pilih akun lain lalu kirim ulang.'
                : 'Belum ada akun email Slip Gaji default yang aktif. Atur di Settings → Akun Email.';
        }

        if ($account->driver !== 'smtp') {
            return "Driver email \"{$account->driver}\" belum didukung untuk slip gaji. Gunakan akun SMTP.";
        }

        return $account;
    }

    /**
     * Kegagalan konfigurasi tidak akan sembuh dengan retry: tandai gagal sekarang.
     */
    private function failWithoutRetry(\Throwable $e): void
    {
        if ($this->job) {
            $this->fail($e); // memanggil failed() lewat queue

            return;
        }

        $this->failed($e);
    }

    /**
     * Dipanggil saat seluruh retry habis (kegagalan permanen).
     */
    public function failed(\Throwable $e): void
    {
        SalarySlip::whereKey($this->salarySlipId)->update([
            'send_status' => SalarySlip::STATUS_FAILED,
            'send_error' => $e->getMessage(),
        ]);

        SendJob::whereKey($this->sendJobId)->update([
            'status' => SendJob::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
