<?php

namespace App\Domains\SalarySlip\Requests;

use App\Core\Http\Requests\BaseRequest;

class ImportSalarySlipRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ];
    }

    protected function rulesForUpdate(): array
    {
        return $this->rulesForCreate();
    }
}
