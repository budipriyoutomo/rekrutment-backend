<?php

namespace App\Domains\Auth\DTO;

use App\Core\Http\Requests\BaseRequest;

class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}

    public static function fromRequest(BaseRequest $request): self
    {
        return new self(
            email: $request->input('email'),
            password: $request->input('password'),
        );
    }
}