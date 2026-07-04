<?php

namespace App\Domains\SalarySlip\Exports\Sheets;

use App\Domains\SalarySlip\Support\SalarySlipColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2: penjelasan format tiap kolom.
 */
class TemplatePetunjukSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    private const TYPE_LABEL = [
        SalarySlipColumns::TYPE_STRING => 'Teks',
        SalarySlipColumns::TYPE_EMAIL  => 'Email',
        SalarySlipColumns::TYPE_PERIOD => 'Periode (YYYY-MM)',
        SalarySlipColumns::TYPE_MONEY  => 'Angka (tanpa Rp/titik)',
        SalarySlipColumns::TYPE_DAYS   => 'Angka (hari)',
    ];

    public function title(): string
    {
        return 'Petunjuk';
    }

    public function headings(): array
    {
        return ['Kolom', 'Label', 'Tipe', 'Wajib', 'Keterangan'];
    }

    public function array(): array
    {
        $rows = [];
        foreach (SalarySlipColumns::COLUMNS as $key => [$label, $type, $required, $hint]) {
            $rows[] = [
                $key,
                $label,
                self::TYPE_LABEL[$type] ?? $type,
                $required ? 'Ya' : 'Tidak',
                $hint,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
