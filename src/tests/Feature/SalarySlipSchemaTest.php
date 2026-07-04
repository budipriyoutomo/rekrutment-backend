<?php

namespace Tests\Feature;

use App\Domains\SalarySlip\Models\ImportBatch;
use App\Domains\SalarySlip\Models\MailAccount;
use App\Domains\SalarySlip\Models\SalarySlip;
use App\Domains\SalarySlip\Models\SendJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 0 — Fondasi & Migrasi Skema.
 * Memastikan skema penuh e-slip (salary_slips + import_batches + mail_accounts + send_jobs)
 * ter-migrasi dengan benar dan model + cast berfungsi.
 */
class SalarySlipSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function slipData(array $override = []): array
    {
        return array_merge([
            'nik'                   => '12345',
            'nama'                  => 'Budi Santoso',
            'jabatan'               => 'Staff',
            'periode'               => '2026-06',
            'cabang'                => 'Jakarta',
            'perusahaan'            => 'PT Maju',
            'email'                 => 'budi@example.com',
            'gaji_pokok'            => 5000000,
            'tunjangan_jabatan'     => 500000,
            'total_penerimaan'      => 5500000,
            'total_potongan'        => 500000,
            'take_home_pay'         => 5000000,
            'hk_hari'               => 22,
            'sistem_pembayaran'     => 'transfer',
            'no_rekening'           => '1234567890',
            'nama_bank'             => 'BCA',
            'atas_nama'             => 'Budi Santoso',
        ], $override);
    }

    public function test_all_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('salary_slips'));
        $this->assertTrue(Schema::hasTable('import_batches'));
        $this->assertTrue(Schema::hasTable('mail_accounts'));
        $this->assertTrue(Schema::hasTable('send_jobs'));
    }

    public function test_salary_slips_has_full_payslip_columns(): void
    {
        $expected = [
            'batch_id', 'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan',
            'tunjangan_transport', 'tunjangan_lain', 'lembur', 'tambahan_gaji',
            'ph_dibayar', 'refund_seragam', 'jumlah_service_charge', 'total_penerimaan',
            'hk_hari', 'alpha_hari', 'ijin_ap_hari', 'sakit_hari', 'cuti_hari',
            'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
            'pph21', 'potongan_seragam', 'koreksi', 'total_potongan', 'take_home_pay',
            'sistem_pembayaran', 'no_rekening', 'nama_bank', 'atas_nama',
            'send_status', 'send_error', 'pdf_path', 'mail_account_id',
            'created_by', 'updated_by',
        ];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('salary_slips', $col),
                "Kolom `{$col}` tidak ada di salary_slips",
            );
        }
    }

    public function test_can_create_slip_with_full_data_and_casts_apply(): void
    {
        $slip = SalarySlip::create($this->slipData());

        $this->assertIsInt($slip->gaji_pokok);
        $this->assertSame(5000000, $slip->gaji_pokok);
        $this->assertIsInt($slip->hk_hari);
        // status default = draft
        $this->assertSame(SalarySlip::STATUS_DRAFT, $slip->send_status);
        $this->assertDatabaseHas('salary_slips', [
            'nik'          => '12345',
            'send_status'  => 'draft',
            'gaji_pokok'   => 5000000,
        ]);
    }

    public function test_batch_relation_groups_slips(): void
    {
        $batch = ImportBatch::create([
            'file_name'    => 'slip_juni.xlsx',
            'total_rows'   => 2,
            'success_rows' => 2,
            'failed_rows'  => 0,
            'errors'       => [],
        ]);

        SalarySlip::create($this->slipData(['batch_id' => $batch->id, 'nik' => 'A1']));
        SalarySlip::create($this->slipData(['batch_id' => $batch->id, 'nik' => 'A2']));

        $this->assertCount(2, $batch->slips);
        $this->assertSame($batch->id, SalarySlip::where('nik', 'A1')->first()->batch->id);
    }

    public function test_import_batch_errors_cast_to_array(): void
    {
        $batch = ImportBatch::create([
            'file_name' => 'x.xlsx',
            'errors'    => [['row' => 3, 'messages' => ['email tidak valid']]],
        ]);

        $fresh = $batch->fresh();
        $this->assertIsArray($fresh->errors);
        $this->assertSame(3, $fresh->errors[0]['row']);
    }

    public function test_mail_account_password_encrypted_at_rest_and_hidden(): void
    {
        $account = MailAccount::create([
            'label'                   => 'HR Team',
            'driver'                  => 'smtp',
            'from_email'              => 'hr@example.com',
            'from_name'               => 'HR Team',
            'smtp_host'               => 'smtp.example.com',
            'smtp_port'               => 587,
            'smtp_username'           => 'hr@example.com',
            'smtp_password_encrypted' => 'super-secret',
            'is_default'              => true,
        ]);

        // Model mendekripsi kembali ke plaintext saat runtime
        $this->assertSame('super-secret', $account->fresh()->smtp_password_encrypted);

        // Tersimpan terenkripsi di DB (bukan plaintext)
        $raw = DB::table('mail_accounts')->where('id', $account->id)->value('smtp_password_encrypted');
        $this->assertNotSame('super-secret', $raw);
        $this->assertNotEmpty($raw);

        // Tidak bocor ke serialisasi API
        $this->assertArrayNotHasKey('smtp_password_encrypted', $account->toArray());
    }

    public function test_send_job_relations(): void
    {
        $slip = SalarySlip::create($this->slipData());
        $job = SendJob::create([
            'salary_slip_id' => $slip->id,
            'status'         => SendJob::STATUS_QUEUED,
            'attempt'        => 0,
        ]);

        $this->assertSame($slip->id, $job->slip->id);
        $this->assertCount(1, $slip->sendJobs);
    }
}
