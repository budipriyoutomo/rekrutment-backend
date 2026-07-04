<?php

namespace App\Domains\SalarySlip\Actions;

use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use App\Domains\SalarySlip\Services\SalarySlipService;
use Illuminate\Support\Str;

/**
 * Dispatch pengiriman slip gaji (bulk) ke queue. Non-blocking: hanya menandai
 * status `queued`, membuat record send_jobs, dan melempar job ke worker.
 *
 * Resend: slip yang statusnya sudah `sent` dilewati (skipped) kecuali $resend = true.
 */
class SendSalarySlipAction
{
    public function __construct(private SalarySlipService $service) {}

    /**
     * @param  string[]  $ids
     * @return array{dispatched:int,skipped:int,job_batch_id:string}
     */
    public function execute(array $ids, ?string $mailAccountId = null, bool $resend = false): array
    {
        $slips = $this->service->getByIds($ids);

        $jobBatchId = (string) Str::uuid();
        $dispatched = 0;
        $skipped    = 0;

        foreach ($slips as $slip) {
            // Idempotency: sudah terkirim & bukan resend -> lewati tanpa mengirim ulang.
            if ($slip->send_status === SalarySlip::STATUS_SENT && ! $resend) {
                $skipped++;

                continue;
            }

            $slip->update([
                'send_status'     => SalarySlip::STATUS_QUEUED,
                'mail_account_id' => $mailAccountId,
                'send_error'      => null,
            ]);

            $sendJob = SendJob::create([
                'salary_slip_id'  => $slip->id,
                'mail_account_id' => $mailAccountId,
                'job_batch_id'    => $jobBatchId,
                'status'          => SendJob::STATUS_QUEUED,
                'attempt'         => 0,
            ]);

            SendSalarySlipJob::dispatch($slip->id, $sendJob->id, $resend)->onQueue('emails');
            $dispatched++;
        }

        return [
            'dispatched'   => $dispatched,
            'skipped'      => $skipped,
            'job_batch_id' => $jobBatchId,
        ];
    }
}
