<?php

namespace App\Domains\User\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Enums\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role'     => ['required', Rule::in(array_column(Role::cases(), 'value'))],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return $this->rulesForCreate();
    }
}
