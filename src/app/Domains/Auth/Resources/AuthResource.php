<?php

namespace App\Domains\Auth\Resources;

use App\Core\Http\Resources\BaseResource;

class AuthResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'access_token' => $this['access_token'],
            'token_type' => $this['token_type'],
            'expires_in' => $this['expires_in'],
        ];
    }
}