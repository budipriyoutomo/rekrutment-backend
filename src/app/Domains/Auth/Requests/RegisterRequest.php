<?php

namespace App\Domains\Auth\Requests;

use App\Core\Http\Requests\BaseRequest;

class RegisterRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }
}
