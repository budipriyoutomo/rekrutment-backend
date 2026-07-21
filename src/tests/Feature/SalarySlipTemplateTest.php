<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Exports\SalarySlipTemplateExport;
use App\Domains\SalarySlip\Exports\Sheets\TemplateDataSheet;
use App\Domains\SalarySlip\Support\SalarySlipColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Phase 1 — Template Excel Download.
 */
class SalarySlipTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_template_endpoint_returns_xlsx_download(): void
    {
        $response = $this->get('/api/salary-slips/template');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'template_salary_slip.xlsx',
            $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type'),
        );
    }

    public function test_data_sheet_headings_match_import_contract(): void
    {
        // Header di sheet Data harus persis = kontrak kolom import (snake_case)
        $this->assertSame(SalarySlipColumns::keys(), (new TemplateDataSheet())->headings());
    }

    public function test_generated_file_headers_and_example_round_trip(): void
    {
        Storage::fake('local');
        Excel::store(new SalarySlipTemplateExport(), 'tpl.xlsx', 'local');

        $sheets = Excel::toArray(new class {}, Storage::disk('local')->path('tpl.xlsx'));

        // Sheet 0 = "Data": baris 0 = header, baris 1 = contoh
        $header = $sheets[0][0];
        $this->assertSame(SalarySlipColumns::keys(), $header);

        // Semua kolom wajib ada di header
        foreach (SalarySlipColumns::requiredKeys() as $req) {
            $this->assertContains($req, $header);
        }

        // Baris contoh terisi & selaras posisinya dengan header
        $example = $sheets[0][1];
        $this->assertSame(count($header), count($example));
        $nikIdx = array_search('nik', $header, true);
        $this->assertSame('12345', (string) $example[$nikIdx]);

        // Sheet 1 = "Petunjuk" ada & punya baris keterangan per kolom
        $this->assertArrayHasKey(1, $sheets);
        $this->assertSame(['Kolom', 'Label', 'Tipe', 'Wajib', 'Keterangan'], $sheets[1][0]);
        $this->assertCount(count(SalarySlipColumns::COLUMNS) + 1, $sheets[1]); // +1 header
    }
}
