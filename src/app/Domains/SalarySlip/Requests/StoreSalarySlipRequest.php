<?php

namespace App\Domains\SalarySlip\Requests;

use App\Core\Http\Requests\BaseRequest;

class StoreSalarySlipRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'nik'           => 'required|string|max:50',
            'nama'          => 'required|string|max:255',
            'jabatan'       => 'required|string|max:255',
            'periode'       => 'required|string|regex:/^\d{4}-\d{2}$/',
            'cabang'        => 'required|string|max:255',
            'perusahaan'    => 'required|string|max:255',
            'take_home_pay' => 'required|integer|min:0',
            'email'         => 'required|email|max:255',
        ];
    }
}
