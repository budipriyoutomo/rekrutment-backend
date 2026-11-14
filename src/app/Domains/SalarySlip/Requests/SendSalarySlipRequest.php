<?php

namespace App\Domains\SalarySlip\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Domains\SalarySlip\Models\MailAccount;
use Illuminate\Validation\Rule;

class SendSalarySlipRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'uuid'],
            // Hanya akun email Slip Gaji yang aktif — bukan akun rekrutmen.
            'mail_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('mail_accounts', 'id')
                    ->where('is_active', true)
                    ->where('purpose', MailAccount::PURPOSE_SALARY_SLIP),
            ],
            'resend' => ['sometimes', 'boolean'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return $this->rulesForCreate();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mail_account_id.exists' => 'Akun email pengirim harus akun Slip Gaji yang aktif.',
            'ids.max' => 'Maksimal 500 slip per pengiriman.',
        ];
    }
}
