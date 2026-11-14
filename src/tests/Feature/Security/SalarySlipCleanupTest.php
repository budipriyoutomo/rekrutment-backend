<?php

namespace Tests\Feature\Security;

use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalarySlipCleanupTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.upload_disk'));
        $this->actingAsUserWithPermissions(['salary-slips']);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function makeSlip(array $o = []): SalarySlip
    {
        static $n = 0;
        $n++;

        return SalarySlip::create(array_merge([
            'nik' => "C{$n}", 'nama' => 'Budi Santoso', 'jabatan' => 'Staff', 'periode' => '2026-06',
            'cabang' => 'Jakarta Selatan', 'perusahaan' => 'PT Maju', 'email' => 'budi@example.com',
            'take_home_pay' => 5000000,
        ], $o));
    }

    private function payslipFile(string $id, ?int $modifiedAt = null): string
    {
        $dir = storage_path('app/payslips');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = "{$dir}/{$id}.pdf";
        file_put_contents($path, '%PDF-1.4 dummy');
        touch($path, $modifiedAt ?? now()->subDays(3)->getTimestamp());
        $this->createdFiles[] = $path;

        return $path;
    }

    public function test_search_is_case_insensitive(): void
    {
        $this->makeSlip(['nama' => 'Budi Santoso']);
        $this->makeSlip(['nama' => 'Sari Dewi', 'email' => 'sari@example.com']);

        $this->getJson('/api/salary-slips?search=BUDI')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.data');

        $this->getJson('/api/salary-slips?cabang=jakarta selatan')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_bulk_delete_removes_pdf_files(): void
    {
        $deleted = $this->makeSlip();
        $kept = $this->makeSlip();
        $deletedPdf = $this->payslipFile($deleted->id);
        $keptPdf = $this->payslipFile($kept->id);

        $this->deleteJson('/api/salary-slips/bulk', ['ids' => [$deleted->id]])
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', 1);

        $this->assertFileDoesNotExist($deletedPdf);
        $this->assertFileExists($keptPdf);
    }

    public function test_prune_removes_orphan_payslip_pdf(): void
    {
        $slip = $this->makeSlip();
        $referenced = $this->payslipFile($slip->id);
        $orphan = $this->payslipFile((string) Str::uuid());
        $recentOrphan = $this->payslipFile((string) Str::uuid(), now()->getTimestamp());

        $this->artisan('storage:prune-orphans', ['--delete' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertFileExists($referenced);
        $this->assertFileDoesNotExist($orphan);
        // File baru (< min-age) dilewati.
        $this->assertFileExists($recentOrphan);
    }

    public function test_prune_dry_run_keeps_orphan_payslip_pdf(): void
    {
        $orphan = $this->payslipFile((string) Str::uuid());

        $this->artisan('storage:prune-orphans')
            ->expectsOutputToContain('payslips/')
            ->assertSuccessful();

        $this->assertFileExists($orphan);
    }
}
