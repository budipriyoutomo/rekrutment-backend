<?php

namespace App\Domains\SalarySlip\Requests;

use App\Core\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class MailAccountRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'label'           => ['required', 'string', 'max:255'],
            'driver'          => ['required', Rule::in(['smtp', 'ses', 'mailgun'])],
            'from_email'      => ['required', 'email', 'max:255'],
            'from_name'       => ['required', 'string', 'max:255'],
            'smtp_host'       => ['nullable', 'string', 'max:255', 'required_if:driver,smtp'],
            'smtp_port'       => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:driver,smtp'],
            'smtp_username'   => ['nullable', 'string', 'max:255'],
            'smtp_password'   => ['nullable', 'string', 'max:255', 'required_if:driver,smtp'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'is_default'      => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'label'           => ['sometimes', 'string', 'max:255'],
            'driver'          => ['sometimes', Rule::in(['smtp', 'ses', 'mailgun'])],
            'from_email'      => ['sometimes', 'email', 'max:255'],
            'from_name'       => ['sometimes', 'string', 'max:255'],
            'smtp_host'       => ['nullable', 'string', 'max:255'],
            'smtp_port'       => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'   => ['nullable', 'string', 'max:255'],
            // Password opsional saat update — kosong = tidak diubah.
            'smtp_password'   => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'is_default'      => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }
}
