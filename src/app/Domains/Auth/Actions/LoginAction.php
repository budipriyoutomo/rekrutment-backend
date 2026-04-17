<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTO\LoginDTO;
use App\Domains\Auth\Services\AuthService;

class LoginAction
{
    public function __construct(
        private AuthService $service
    ) {}

    public function execute(LoginDTO $dto)
    {
        return $this->service->login(
            $dto->email,
            $dto->password
        );
    }
}