<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Actions\GeneratePayslipPdfAction;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Services\PayslipDocumentBuilder;
use App\Domains\SalarySlip\Services\PayslipPdfConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalarySlipPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function makeSlip(array $o = []): SalarySlip
    {
        return SalarySlip::create(array_merge([
            'nik' => '12345', 'nama' => 'Budi Santoso', 'jabatan' => 'Staff',
            'periode' => '2026-06', 'cabang' => 'Jakarta', 'perusahaan' => 'PT Maju',
            'email' => 'budi@example.com',
            'gaji_pokok' => 5000000, 'tunjangan_jabatan' => 500000,
            'total_penerimaan' => 5500000, 'bpjs_kesehatan' => 100000,
            'total_potongan' => 100000, 'take_home_pay' => 5400000,
            'hk_hari' => 22, 'sistem_pembayaran' => 'Transfer',
            'no_rekening' => '1234567890', 'nama_bank' => 'BCA', 'atas_nama' => 'Budi Santoso',
        ], $o));
    }

    /** Baca isi teks word/document.xml dari sebuah file .docx */
    private function readDocxText(string $path): string
    {
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'Gagal membuka docx');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml ?: '';
    }

    // ------------------------------------------------------------- docx builder

    public function test_docx_contains_all_key_fields(): void
    {
        $slip = $this->makeSlip();
        $tmp = tempnam(sys_get_temp_dir(), 'payslip') . '.docx';

        app(PayslipDocumentBuilder::class)->build($slip, $tmp);

        $xml = $this->readDocxText($tmp);
        @unlink($tmp);

        // Kop & struktur
        $this->assertStringContainsString('SLIP GAJI', $xml);
        $this->assertStringContainsString('PENERIMAAN', $xml);
        $this->assertStringContainsString('PEMOTONGAN', $xml);
        $this->assertStringContainsString('TAKE HOME PAY', $xml);

        // Identitas
        $this->assertStringContainsString('Budi Santoso', $xml);
        $this->assertStringContainsString('12345', $xml);
        $this->assertStringContainsString('2026-06', $xml);

        // Nominal terformat Rupiah
        $this->assertStringContainsString('5.000.000,00', $xml); // gaji pokok
        $this->assertStringContainsString('5.400.000,00', $xml); // take home pay

        // Absensi
        $this->assertStringContainsString('22 Hari', $xml);

        // Info pembayaran
        $this->assertStringContainsString('1234567890', $xml);
        $this->assertStringContainsString('BCA', $xml);
    }

    public function test_docx_is_a5_landscape(): void
    {
        $slip = $this->makeSlip();
        $tmp = tempnam(sys_get_temp_dir(), 'payslip') . '.docx';

        app(PayslipDocumentBuilder::class)->build($slip, $tmp);
        $xml = $this->readDocxText($tmp);
        @unlink($tmp);

        $this->assertStringContainsString('w:orient="landscape"', $xml);
        // A5: sisi panjang 11906 twips, sisi pendek 8391 twips
        $this->assertMatchesRegularExpression('/w:w="11906"/', $xml);
        $this->assertMatchesRegularExpression('/w:h="8391"/', $xml);
    }

    // --------------------------------------------------------------- pdf + api

    public function test_generate_pdf_creates_file_and_saves_path(): void
    {
        if (! app(PayslipPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak tersedia di environment ini.');
        }

        $slip = $this->makeSlip();
        $path = app(GeneratePayslipPdfAction::class)->execute($slip);

        $this->assertFileExists($path);
        $this->assertSame('%PDF', substr(file_get_contents($path), 0, 4), 'Bukan file PDF valid');

        $slip->refresh();
        $this->assertSame("payslips/{$slip->id}.pdf", $slip->pdf_path);

        @unlink($path);
    }

    public function test_generate_pdf_is_reused_when_already_exists(): void
    {
        if (! app(PayslipPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak tersedia di environment ini.');
        }

        $slip = $this->makeSlip();
        $action = app(GeneratePayslipPdfAction::class);

        $path = $action->execute($slip);
        $mtime = filemtime($path);

        // Panggil lagi tanpa force -> tidak regenerate (mtime sama)
        clearstatcache();
        $again = $action->execute($slip->fresh());

        $this->assertSame($path, $again);
        $this->assertSame($mtime, filemtime($again));

        @unlink($path);
    }

    public function test_generated_pdf_is_exactly_one_page(): void
    {
        if (! app(PayslipPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak tersedia di environment ini.');
        }

        $slip = $this->makeSlip();
        $path = app(GeneratePayslipPdfAction::class)->execute($slip);

        $fpdi = new \setasign\Fpdi\Fpdi();
        $pages = $fpdi->setSourceFile($path);

        $this->assertSame(1, $pages, 'Slip gaji harus muat dalam 1 halaman, tidak boleh melintas 2 halaman.');

        @unlink($path);
    }

    public function test_pdf_endpoint_downloads_file(): void
    {
        if (! app(PayslipPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak tersedia di environment ini.');
        }

        $slip = $this->makeSlip();

        $response = $this->get("/api/salary-slips/{$slip->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));

        @unlink(storage_path("app/payslips/{$slip->id}.pdf"));
    }

    public function test_pdf_endpoint_404_for_unknown_id(): void
    {
        $this->getJson('/api/salary-slips/non-existent-id/pdf')->assertStatus(404);
    }
}
