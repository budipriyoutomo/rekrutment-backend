<?php

namespace App\Domains\Auth\Requests;

use App\Core\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }
}