<?php

namespace App\Domains\SalarySlip\Validators;

use App\Domains\SalarySlip\Support\SalarySlipColumns;

/**
 * Validasi satu baris import salary slip.
 * Mengembalikan array pesan error (string). Kosong = baris valid.
 */
class SalaryRowValidator
{
    /**
     * @param  array<string,mixed>  $row  baris ter-map (key snake_case = kolom)
     * @return string[] daftar pesan error
     */
    public function validate(array $row): array
    {
        $errors = [];

        // 1. Kolom wajib tidak boleh kosong
        foreach (SalarySlipColumns::requiredKeys() as $key) {
            $val = $row[$key] ?? null;
            if ($val === null || trim((string) $val) === '') {
                $label = SalarySlipColumns::COLUMNS[$key][0];
                $errors[] = "Kolom {$label} ({$key}) wajib diisi.";
            }
        }

        // 2. Format email
        $email = $row['email'] ?? null;
        if ($email !== null && trim((string) $email) !== ''
            && ! filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email tidak valid: {$email}.";
        }

        // 3. Format periode YYYY-MM
        $periode = trim((string) ($row['periode'] ?? ''));
        if ($periode !== '' && ! $this->isValidPeriode($periode)) {
            $errors[] = "Periode harus format YYYY-MM (bulan 01-12): {$periode}.";
        }

        // 4. Kolom numerik (uang/hari) harus angka bila diisi
        foreach (SalarySlipColumns::numericKeys() as $key) {
            $val = $row[$key] ?? null;
            if ($val === null || $val === '') {
                continue; // opsional -> default 0
            }
            if (! is_numeric($val)) {
                $label = SalarySlipColumns::COLUMNS[$key][0];
                $errors[] = "Kolom {$label} ({$key}) harus berupa angka: {$val}.";
            }
        }

        return $errors;
    }

    private function isValidPeriode(string $periode): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
            return false;
        }
        $month = (int) $m[2];

        return $month >= 1 && $month <= 12;
    }
}
