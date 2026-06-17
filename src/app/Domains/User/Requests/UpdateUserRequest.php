<?php

namespace App\Domains\User\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Enums\Role;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'role'     => ['sometimes', Rule::in(array_column(Role::cases(), 'value'))],
        ];
    }
}
