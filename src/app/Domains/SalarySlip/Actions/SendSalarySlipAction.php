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
 * $resendOnly (actor hanya berizin Report): slip yang belum pernah terkirim
 * (`sent_at` kosong) dilewati — Report hanya boleh mengirim ulang.
 */
class SendSalarySlipAction
{
    public function __construct(private SalarySlipService $service) {}

    /**
     * @param  string[]  $ids
     * @return array{dispatched:int,skipped:int,skipped_details:array<int,array{id:string,reason:string}>,job_batch_id:string}
     */
    public function execute(array $ids, ?string $mailAccountId = null, bool $resend = false, bool $resendOnly = false): array
    {
        $slips = $this->service->getByIds($ids);

        $jobBatchId = (string) Str::uuid();
        $dispatched = 0;
        $skippedDetails = [];

        foreach ($slips as $slip) {
            if ($resendOnly && $slip->sent_at === null) {
                $skippedDetails[] = [
                    'id' => $slip->id,
                    'reason' => 'Belum pernah dikirim — izin Report hanya bisa mengirim ulang.',
                ];

                continue;
            }

            // Idempotency: sudah terkirim & bukan resend -> lewati tanpa mengirim ulang.
            if ($slip->send_status === SalarySlip::STATUS_SENT && ! $resend) {
                $skippedDetails[] = ['id' => $slip->id, 'reason' => 'Sudah terkirim.'];

                continue;
            }

            $slip->update([
                'send_status' => SalarySlip::STATUS_QUEUED,
                'mail_account_id' => $mailAccountId,
                'send_error' => null,
            ]);

            $sendJob = SendJob::create([
                'salary_slip_id' => $slip->id,
                'mail_account_id' => $mailAccountId,
                'job_batch_id' => $jobBatchId,
                'status' => SendJob::STATUS_QUEUED,
                'attempt' => 0,
            ]);

            SendSalarySlipJob::dispatch($slip->id, $sendJob->id, $resend)->onQueue('emails');
            $dispatched++;
        }

        return [
            'dispatched' => $dispatched,
            'skipped' => count($skippedDetails),
            'skipped_details' => $skippedDetails,
            'job_batch_id' => $jobBatchId,
        ];
    }
}
