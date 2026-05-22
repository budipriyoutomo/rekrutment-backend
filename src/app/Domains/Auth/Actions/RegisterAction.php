<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTO\RegisterDTO;
use App\Domains\Auth\Services\AuthService;

class RegisterAction
{
    public function __construct(
        private AuthService $service
    ) {}

    public function execute(RegisterDTO $dto)
    {
        return $this->service->registerUser(
            $dto->name,
            $dto->email,
            $dto->password
        );
    }
}
