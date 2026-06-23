<?php

namespace App\Domains\SalarySlip\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalarySlipResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'nik'           => $this->nik,
            'nama'          => $this->nama,
            'jabatan'       => $this->jabatan,
            'periode'       => $this->periode,
            'cabang'        => $this->cabang,
            'perusahaan'    => $this->perusahaan,
            'takeHomePay'   => $this->take_home_pay,
            'email'         => $this->email,
            'sentAt'        => $this->sent_at?->toIso8601String(),
            'createdAt'     => $this->created_at?->toIso8601String(),
        ];
    }
}
