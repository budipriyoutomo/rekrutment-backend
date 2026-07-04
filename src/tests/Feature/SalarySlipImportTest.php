<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Models\ImportBatch;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SalarySlipImportTest extends TestCase
{
    use RefreshDatabase;

    /** Kolom minimal (semua wajib + beberapa nominal) untuk file uji. */
    private const HEADER = [
        'nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'email',
        'gaji_pokok', 'total_penerimaan', 'total_potongan', 'take_home_pay',
    ];

    private function row(array $o = []): array
    {
        return array_merge([
            'nik' => '1', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'gaji_pokok' => 5000000, 'total_penerimaan' => 5000000,
            'total_potongan' => 0, 'take_home_pay' => 5000000,
        ], $o);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function csvUpload(array $rows, array $header = self::HEADER): UploadedFile
    {
        $lines = [implode(',', $header)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($k) => $r[$k] ?? '', $header));
        }

        return UploadedFile::fake()->createWithContent('slip.csv', implode("\n", $lines));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function xlsxUpload(array $rows, array $header = self::HEADER): UploadedFile
    {
        $data = array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? '', $header), $rows);

        $export = new class($header, $data) implements FromArray, WithHeadings {
            public function __construct(private array $h, private array $d) {}
            public function headings(): array { return $this->h; }
            public function array(): array { return $this->d; }
        };

        $binary = Excel::raw($export, ExcelFormat::XLSX);
        $tmp = tempnam(sys_get_temp_dir(), 'slip') . '.xlsx';
        file_put_contents($tmp, $binary);

        return new UploadedFile($tmp, 'slip.xlsx', null, null, true);
    }

    private function action(): ImportSalarySlipsAction
    {
        return app(ImportSalarySlipsAction::class);
    }

    // ---------------------------------------------------------------- csv core

    public function test_import_partial_success_with_invalid_rows(): void
    {
        $file = $this->csvUpload([
            $this->row(['nik' => '1', 'email' => 'a@example.com']),
            $this->row(['nik' => '2', 'email' => 'bad-email']),           // invalid email
            $this->row(['nik' => '3', 'periode' => '2026-13']),           // invalid periode
            $this->row(['nik' => '4', 'nama' => '', 'email' => 'd@e.com']), // missing nama
            $this->row(['nik' => '5', 'email' => 'e@example.com']),
        ]);

        $summary = $this->action()->execute($file);

        $this->assertSame(5, $summary['total_rows']);
        $this->assertSame(2, $summary['success_rows']);
        $this->assertSame(3, $summary['failed_rows']);
        $this->assertCount(2, SalarySlip::all());

        // Error mencantumkan nomor baris (baris 3 = nik 2 invalid email)
        $rows = array_column($summary['errors'], 'row');
        $this->assertContains(3, $rows);
        $this->assertContains(4, $rows);
        $this->assertContains(5, $rows);
    }

    public function test_valid_rows_saved_with_batch_id_and_draft_status(): void
    {
        $file = $this->csvUpload([$this->row(['nik' => '1', 'email' => 'a@example.com'])]);
        $summary = $this->action()->execute($file);

        $slip = SalarySlip::first();
        $this->assertSame($summary['batch_id'], $slip->batch_id);
        $this->assertSame(SalarySlip::STATUS_DRAFT, $slip->send_status);
        $this->assertSame(5000000, $slip->gaji_pokok);
    }

    public function test_import_batch_record_persisted_with_errors(): void
    {
        $file = $this->csvUpload([
            $this->row(['nik' => '1', 'email' => 'a@example.com']),
            $this->row(['nik' => '2', 'email' => 'bad']),
        ]);
        $summary = $this->action()->execute($file);

        $batch = ImportBatch::find($summary['batch_id']);
        $this->assertNotNull($batch);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertIsArray($batch->errors);
        $this->assertSame(3, $batch->errors[0]['row']);
    }

    // ------------------------------------------------------------- duplicates

    public function test_duplicate_within_file_is_rejected(): void
    {
        $file = $this->csvUpload([
            $this->row(['nik' => '1', 'periode' => '2026-06', 'email' => 'a@example.com']),
            $this->row(['nik' => '1', 'periode' => '2026-06', 'email' => 'a@example.com']), // dup
        ]);
        $summary = $this->action()->execute($file);

        $this->assertSame(1, $summary['success_rows']);
        $this->assertSame(1, $summary['failed_rows']);
        $this->assertStringContainsStringIgnoringCase('duplikat', $summary['errors'][0]['messages'][0]);
    }

    public function test_duplicate_against_existing_db_is_rejected(): void
    {
        SalarySlip::create([
            'nik' => '1', 'nama' => 'Lama', 'jabatan' => 'X', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'old@example.com',
            'take_home_pay' => 4000000,
        ]);

        $file = $this->csvUpload([$this->row(['nik' => '1', 'periode' => '2026-06', 'email' => 'new@example.com'])]);
        $summary = $this->action()->execute($file);

        $this->assertSame(0, $summary['success_rows']);
        $this->assertSame(1, $summary['failed_rows']);
        // data lama tidak berubah (bukan upsert)
        $this->assertSame('Lama', SalarySlip::where('nik', '1')->first()->nama);
        $this->assertSame(1, SalarySlip::where('nik', '1')->count());
    }

    // ---------------------------------------------------------------- warnings

    public function test_total_mismatch_produces_warning_but_row_saved(): void
    {
        $file = $this->csvUpload([
            $this->row(['nik' => '1', 'email' => 'a@example.com', 'total_penerimaan' => 9999999]),
        ]);
        $summary = $this->action()->execute($file);

        $this->assertSame(1, $summary['success_rows']); // tetap disimpan
        $this->assertNotEmpty($summary['warnings']);
        $this->assertSame(2, $summary['warnings'][0]['row']);
    }

    // -------------------------------------------------------------------- xlsx

    public function test_import_from_xlsx(): void
    {
        $file = $this->xlsxUpload([
            $this->row(['nik' => '1', 'email' => 'a@example.com']),
            $this->row(['nik' => '2', 'email' => 'b@example.com']),
        ]);
        $summary = $this->action()->execute($file);

        $this->assertSame(2, $summary['success_rows']);
        $this->assertSame(0, $summary['failed_rows']);
        $this->assertCount(2, SalarySlip::all());
    }

    // ---------------------------------------------------------------- endpoint

    public function test_import_endpoint_returns_summary(): void
    {
        $file = $this->csvUpload([
            $this->row(['nik' => '1', 'email' => 'a@example.com']),
            $this->row(['nik' => '2', 'email' => 'bad']),
        ]);

        $this->postJson('/api/salary-slips/import', ['file' => $file])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.success_rows', 1)
            ->assertJsonPath('data.failed_rows', 1)
            ->assertJsonStructure(['data' => ['batch_id', 'total_rows', 'errors', 'warnings']]);
    }

    public function test_missing_required_header_rejects_file(): void
    {
        // header tanpa kolom wajib 'email'
        $header = ['nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan'];
        $file = $this->csvUpload([$this->row()], $header);

        $this->postJson('/api/salary-slips/import', ['file' => $file])
            ->assertStatus(422);
    }
}
