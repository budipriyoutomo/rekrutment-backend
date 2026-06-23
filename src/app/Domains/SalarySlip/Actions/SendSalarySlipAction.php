<?php

namespace App\Domains\SalarySlip\Actions;

use App\Domains\SalarySlip\Jobs\SendSalarySlipJob;
use App\Domains\SalarySlip\Services\SalarySlipService;

class SendSalarySlipAction
{
    public function __construct(private SalarySlipService $service) {}

    public function execute(array $ids): int
    {
        $slips = $this->service->getByIds($ids);

        foreach ($slips as $slip) {
            SendSalarySlipJob::dispatch($slip->id)->onQueue('emails');
        }

        return $slips->count();
    }
}
