<?php

namespace App\Domains\SalarySlip\Requests;

use App\Core\Http\Requests\BaseRequest;

class ImportSalarySlipRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ];
    }
}
