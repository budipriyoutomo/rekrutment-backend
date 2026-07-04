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
 * Sheet 1: header snake_case (kontrak import) + 1 baris contoh.
 */
class TemplateDataSheet implements FromArray, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function title(): string
    {
        return 'Data';
    }

    public function headings(): array
    {
        return SalarySlipColumns::keys();
    }

    public function array(): array
    {
        $example = SalarySlipColumns::exampleRow();

        // Susun sesuai urutan kolom kanonik
        $row = array_map(
            fn (string $key) => $example[$key] ?? '',
            SalarySlipColumns::keys(),
        );

        return [$row];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header tebal
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
