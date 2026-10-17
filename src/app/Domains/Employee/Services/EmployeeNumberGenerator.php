<?php

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Setting\Models\CompanySetting;
use Carbon\CarbonInterface;

/**
 * NIK karyawan: {PREFIX}-{TAHUN MULAI}-{URUT 4 DIGIT}, mis. EMP-2026-0001.
 * Prefix diatur di Company Settings (`employee_id_prefix`), default "EMP".
 */
class EmployeeNumberGenerator
{
    public const DEFAULT_PREFIX = 'EMP';

    public function next(CarbonInterface $startDate): string
    {
        $prefix = strtoupper(trim((string) CompanySetting::get('employee_id_prefix'))) ?: self::DEFAULT_PREFIX;
        $base = "{$prefix}-{$startDate->format('Y')}-";

        $last = Employee::where('employee_number', 'like', $base . '%')
            ->pluck('employee_number')
            ->map(fn (string $n) => (int) substr($n, strlen($base)))
            ->max() ?? 0;

        return $base . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
