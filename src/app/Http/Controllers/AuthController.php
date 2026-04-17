<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\DTO\LoginDTO;
use App\Domains\Auth\Actions\LoginAction;
use App\Domains\Auth\Services\AuthService;

class AuthController extends BaseApiController
{
    public function login(LoginRequest $request, LoginAction $action)
    {
        $dto = LoginDTO::fromRequest($request);

        $result = $action->execute($dto);

        return $this->success($result, 'Login berhasil');
    }

    public function me(AuthService $service)
    {
        return $this->success($service->me());
    }

    public function logout(AuthService $service)
    {
        $service->logout();

        return $this->success(null, 'Logout berhasil');
    }

    public function refresh(AuthService $service)
    {
        return $this->success($service->refresh(), 'Token refreshed');
    }
}