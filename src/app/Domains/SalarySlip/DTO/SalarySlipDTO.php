<?php

namespace App\Domains\SalarySlip\DTO;

class SalarySlipDTO
{
    public function __construct(
        public readonly string $nik,
        public readonly string $nama,
        public readonly string $jabatan,
        public readonly string $periode,
        public readonly string $cabang,
        public readonly string $perusahaan,
        public readonly int    $take_home_pay,
        public readonly string $email,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nik:           $data['nik'],
            nama:          $data['nama'],
            jabatan:       $data['jabatan'],
            periode:       $data['periode'],
            cabang:        $data['cabang'],
            perusahaan:    $data['perusahaan'],
            take_home_pay: (int) $data['take_home_pay'],
            email:         $data['email'],
        );
    }

    public function toArray(): array
    {
        return [
            'nik'           => $this->nik,
            'nama'          => $this->nama,
            'jabatan'       => $this->jabatan,
            'periode'       => $this->periode,
            'cabang'        => $this->cabang,
            'perusahaan'    => $this->perusahaan,
            'take_home_pay' => $this->take_home_pay,
            'email'         => $this->email,
        ];
    }
}
