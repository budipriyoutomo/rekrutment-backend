<?php

namespace Tests\Feature\Security;

use App\Domains\SalarySlip\Actions\ImportSalarySlipsAction;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Services\SalarySlipService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalarySlipUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_14_000001_add_unique_nik_periode_to_salary_slips_table.php';

    private const HEADER = [
        'nik', 'nama', 'jabatan', 'periode', 'cabang', 'perusahaan', 'email',
        'gaji_pokok', 'total_penerimaan', 'total_potongan', 'take_home_pay',
    ];

    private function makeSlip(array $o = []): SalarySlip
    {
        return SalarySlip::create(array_merge([
            'nik' => '1001', 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000,
        ], $o));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function csvUpload(array $rows): UploadedFile
    {
        $lines = [implode(',', self::HEADER)];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($k) => $r[$k] ?? '', self::HEADER));
        }

        return UploadedFile::fake()->createWithContent('slip.csv', implode("\n", $lines));
    }

    private function row(string $nik): array
    {
        return [
            'nik' => $nik, 'nama' => 'Budi', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju', 'email' => "k{$nik}@example.com",
            'gaji_pokok' => 5000000, 'total_penerimaan' => 5000000,
            'total_potongan' => 0, 'take_home_pay' => 5000000,
        ];
    }

    public function test_database_rejects_duplicate_nik_periode(): void
    {
        $this->makeSlip();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->makeSlip(['nama' => 'Budi Kembar']);
    }

    public function test_same_nik_different_periode_is_allowed(): void
    {
        $this->makeSlip(['periode' => '2026-06']);
        $this->makeSlip(['periode' => '2026-07']);

        $this->assertDatabaseCount('salary_slips', 2);
    }

    public function test_import_reports_race_duplicate_as_row_error(): void
    {
        $this->actingAsAdmin();

        // Simulasi import paralel: baris NIK 2002 sudah masuk DB setelah
        // pengecekan duplikat awal (pre-load) selesai.
        $this->makeSlip(['nik' => '2002']);
        $this->partialMock(SalarySlipService::class, function ($mock) {
            $mock->shouldReceive('existingNikPeriodeKeys')->andReturn([]);
        });

        $summary = app(ImportSalarySlipsAction::class)->execute($this->csvUpload([
            $this->row('2001'),
            $this->row('2002'),
            $this->row('2003'),
        ]));

        $this->assertSame(2, $summary['success_rows']);
        $this->assertSame(1, $summary['failed_rows']);
        $this->assertSame(3, $summary['errors'][0]['row']);
        $this->assertStringContainsString('Duplikat', $summary['errors'][0]['messages'][0]);
        $this->assertSame(1, SalarySlip::where('nik', '2002')->count());
        $this->assertDatabaseHas('salary_slips', ['nik' => '2001']);
        $this->assertDatabaseHas('salary_slips', ['nik' => '2003']);
    }

    public function test_unique_migration_fails_clearly_when_duplicates_exist(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        $this->makeSlip(['nik' => '3003']);
        $this->makeSlip(['nik' => '3003', 'nama' => 'Ganda']);

        try {
            $migration->up();
            $this->fail('Migrasi seharusnya gagal bila ada duplikat.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('3003', $e->getMessage());
            $this->assertStringContainsString('2026-06', $e->getMessage());
        }

        // Data tidak dihapus otomatis.
        $this->assertSame(2, SalarySlip::where('nik', '3003')->count());
        $this->assertFalse(Schema::hasIndex('salary_slips', 'salary_slips_nik_periode_unique'));
    }
}
