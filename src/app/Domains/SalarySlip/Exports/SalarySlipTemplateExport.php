<?php

namespace App\Domains\SalarySlip\Exports;

use App\Domains\SalarySlip\Exports\Sheets\TemplateDataSheet;
use App\Domains\SalarySlip\Exports\Sheets\TemplatePetunjukSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template import salary slip (.xlsx) — 2 sheet:
 *   1. "Data"     : header snake_case + 1 baris contoh (siap diisi & di-import balik)
 *   2. "Petunjuk" : keterangan format tiap kolom
 */
class SalarySlipTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TemplateDataSheet(),
            new TemplatePetunjukSheet(),
        ];
    }
}
