<?php

namespace App\Domains\SalarySlip\Actions;

use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Services\SalarySlipService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImportSalarySlipsAction
{
    private const REQUIRED_COLUMNS = ['nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'take_home_pay', 'email'];

    public function __construct(private SalarySlipService $service) {}

    public function execute(UploadedFile $file): int
    {
        $lines = array_filter(explode("\n", str_replace("\r", "", $file->get())));

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['file' => 'File CSV kosong atau hanya berisi header.']);
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $headers = array_map('strtolower', $headers);

        $missing = array_diff(self::REQUIRED_COLUMNS, $headers);
        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: ' . implode(', ', $missing),
            ]);
        }

        $idx = array_flip($headers);
        $dtos = [];

        foreach ($lines as $line) {
            $cols = str_getcsv(trim($line));
            if (count($cols) < count($headers)) continue;

            $dtos[] = SalarySlipDTO::fromArray([
                'nik'           => trim($cols[$idx['nik']] ?? ''),
                'nama'          => trim($cols[$idx['nama']] ?? ''),
                'jabatan'       => trim($cols[$idx['jabatan']] ?? ''),
                'periode'       => trim($cols[$idx['periode']] ?? ''),
                'cabang'        => trim($cols[$idx['cabang']] ?? ''),
                'perusahaan'    => trim($cols[$idx['perusahaan']] ?? ''),
                'take_home_pay' => (int) ($cols[$idx['take_home_pay']] ?? 0),
                'email'         => trim($cols[$idx['email']] ?? ''),
            ]);
        }

        if (empty($dtos)) {
            throw ValidationException::withMessages(['file' => 'Tidak ada data valid yang dapat diimport.']);
        }

        return $this->service->bulkInsert($dtos);
    }
}
