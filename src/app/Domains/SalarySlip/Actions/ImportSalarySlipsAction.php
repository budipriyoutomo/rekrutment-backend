<?php

namespace App\Domains\SalarySlip\Actions;

use App\Domains\SalarySlip\DTO\SalarySlipDTO;
use App\Domains\SalarySlip\Models\ImportBatch;
use App\Domains\SalarySlip\Services\SalarySlipCalculator;
use App\Domains\SalarySlip\Services\SalarySlipService;
use App\Domains\SalarySlip\Support\SalarySlipColumns;
use App\Domains\SalarySlip\Validators\SalaryRowValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import multi-baris salary slip (.xlsx / .csv) — sinkron, partial success.
 *
 * Aturan bisnis (dikonfirmasi):
 *   - Baris invalid TIDAK menghentikan baris lain (partial success).
 *   - Duplikat (nik, periode) — baik terhadap DB maupun antar-baris di file — DITOLAK.
 *   - Nilai total diambil dari file apa adanya; ketidakcocokan hanya jadi WARNING.
 */
class ImportSalarySlipsAction
{
    public function __construct(
        private SalarySlipService    $service,
        private SalaryRowValidator   $validator,
        private SalarySlipCalculator $calculator,
    ) {}

    /**
     * @return array{batch_id:string,total_rows:int,success_rows:int,failed_rows:int,errors:array,warnings:array}
     */
    public function execute(UploadedFile $file): array
    {
        [$headerMap, $dataRows] = $this->parse($file);

        // Header wajib harus lengkap — kalau tidak, tolak seluruh file.
        $missing = array_diff(SalarySlipColumns::requiredKeys(), array_keys($headerMap));
        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan di header: ' . implode(', ', $missing),
            ]);
        }

        $batchId = (string) Str::uuid();

        // Pre-load duplikat dari DB (hanya untuk nik & periode yang muncul di file).
        $niks = $periodes = [];
        foreach ($dataRows as $r) {
            $niks[]     = (string) ($r['nik'] ?? '');
            $periodes[] = (string) ($r['periode'] ?? '');
        }
        $existing = $this->service->existingNikPeriodeKeys($niks, $periodes);

        $errors   = [];
        $warnings = [];
        $dtos     = [];
        $seen     = []; // "nik|periode" yang sudah diproses dalam file ini

        foreach ($dataRows as $offset => $row) {
            $rowNum = $offset + 2; // +1 header, +1 karena 1-indexed

            // 1. Validasi format per baris
            $rowErrors = $this->validator->validate($row);
            if (! empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'messages' => $rowErrors];
                continue;
            }

            // 2. Tolak duplikat (nik, periode) — DB & antar-baris
            $key = trim((string) $row['nik']) . '|' . trim((string) $row['periode']);
            if (isset($existing[$key]) || isset($seen[$key])) {
                $errors[] = [
                    'row'      => $rowNum,
                    'messages' => ["Duplikat: NIK {$row['nik']} periode {$row['periode']} sudah ada, baris ditolak."],
                ];
                continue;
            }
            $seen[$key] = true;

            // 3. Cek konsistensi total (warning, tidak menolak)
            $rowWarnings = $this->calculator->warnings($row);
            if (! empty($rowWarnings)) {
                $warnings[] = ['row' => $rowNum, 'messages' => $rowWarnings];
            }

            $dtos[] = SalarySlipDTO::fromArray(array_merge($row, ['batch_id' => $batchId]));
        }

        $successRows = empty($dtos) ? 0 : $this->service->bulkInsert($dtos);

        ImportBatch::create([
            'id'           => $batchId,
            'file_name'    => $file->getClientOriginalName() ?? 'import.xlsx',
            'total_rows'   => count($dataRows),
            'success_rows' => $successRows,
            'failed_rows'  => count($errors),
            'errors'       => $errors,
            'uploaded_by'  => Auth::id(),
        ]);

        return [
            'batch_id'     => $batchId,
            'total_rows'   => count($dataRows),
            'success_rows' => $successRows,
            'failed_rows'  => count($errors),
            'errors'       => $errors,
            'warnings'     => $warnings,
        ];
    }

    /**
     * Parse file menjadi header-map + baris data ter-map (key snake_case).
     *
     * @return array{0: array<string,int>, 1: array<int,array<string,mixed>>}
     */
    private function parse(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class {}, $file);
        $sheet  = $sheets[0] ?? [];

        if (count($sheet) < 2) {
            throw ValidationException::withMessages([
                'file' => 'File kosong atau hanya berisi header.',
            ]);
        }

        // Baris pertama = header
        $headerRow = array_shift($sheet);
        $headerMap = [];
        foreach ($headerRow as $idx => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') {
                $headerMap[$key] = $idx;
            }
        }

        $known    = SalarySlipColumns::keys();
        $dataRows = [];
        foreach ($sheet as $cells) {
            // Lewati baris kosong total
            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $row = [];
            foreach ($known as $col) {
                if (isset($headerMap[$col])) {
                    $val = $cells[$headerMap[$col]] ?? null;
                    $row[$col] = is_string($val) ? trim($val) : $val;
                }
            }
            $dataRows[] = $row;
        }

        return [$headerMap, $dataRows];
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c !== null && trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }
}
