<?php

namespace App\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    protected array $hidden = [
        'password',
        'remember_token'
    ];

    public function toArray($request): array
    {
        return collect(parent::toArray($request))
            ->except($this->hidden)
            ->map(fn($value) => $this->format($value))
            ->toArray();
    }

    protected function format($value)
    {
        if (is_numeric($value)) return $value + 0;
        return $value;
    }
}