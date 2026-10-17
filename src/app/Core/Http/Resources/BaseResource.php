<?php

namespace App\Core\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    protected array $hidden = [
        'password',
        'remember_token'
    ];

    // Sengaja TIDAK mengonversi string numerik jadi number secara otomatis:
    // field teks seperti phone "0812...", room "101", salary_range "5000000"
    // ikut berubah tipe (leading zero hilang, frontend crash). Field numerik
    // di-cast eksplisit di masing-masing resource.
    public function toArray($request): array
    {
        return collect(parent::toArray($request))
            ->except($this->hidden)
            ->toArray();
    }

    protected function formatArray(array $data): array
    {
        return collect($data)
            ->except($this->hidden)
            ->toArray();
    }
}
