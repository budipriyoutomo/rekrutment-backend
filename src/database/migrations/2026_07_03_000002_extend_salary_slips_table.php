<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            // --- grouping / audit ---
            if (!Schema::hasColumn('salary_slips', 'batch_id')) {
                $table->uuid('batch_id')->nullable()->after('id');
                $table->index('batch_id');
            }

            // --- komponen penerimaan (nominal disimpan integer, tanpa format) ---
            $money = [
                'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
                'tunjangan_lain', 'lembur', 'tambahan_gaji', 'ph_dibayar', 'refund_seragam',
                'jumlah_service_charge', 'total_penerimaan',
                // --- potongan ---
                'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
                'pph21', 'potongan_seragam', 'koreksi', 'total_potongan',
            ];
            foreach ($money as $col) {
                if (!Schema::hasColumn('salary_slips', $col)) {
                    $table->bigInteger($col)->default(0)->after('take_home_pay');
                }
            }

            // --- absensi (hari) ---
            foreach (['hk_hari', 'alpha_hari', 'ijin_ap_hari', 'sakit_hari', 'cuti_hari'] as $col) {
                if (!Schema::hasColumn('salary_slips', $col)) {
                    $table->integer($col)->default(0)->after('take_home_pay');
                }
            }

            // --- info pembayaran ---
            foreach (['sistem_pembayaran', 'no_rekening', 'nama_bank', 'atas_nama'] as $col) {
                if (!Schema::hasColumn('salary_slips', $col)) {
                    $table->string($col)->nullable()->after('take_home_pay');
                }
            }

            // --- lifecycle pengiriman ---
            if (!Schema::hasColumn('salary_slips', 'send_status')) {
                // draft | queued | processing | sent | failed
                $table->string('send_status')->default('draft')->after('atas_nama');
                $table->index('send_status');
            }
            if (!Schema::hasColumn('salary_slips', 'send_error')) {
                $table->text('send_error')->nullable()->after('send_status');
            }
            if (!Schema::hasColumn('salary_slips', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('send_error');
            }
            if (!Schema::hasColumn('salary_slips', 'mail_account_id')) {
                $table->uuid('mail_account_id')->nullable()->after('pdf_path');
                $table->index('mail_account_id');
            }

            // --- userstamps (dibutuhkan trait HasUserstamps) ---
            if (!Schema::hasColumn('salary_slips', 'created_by')) {
                $table->uuid('created_by')->nullable();
            }
            if (!Schema::hasColumn('salary_slips', 'updated_by')) {
                $table->uuid('updated_by')->nullable();
            }
        });

        // Backfill status untuk baris lama: yang sudah pernah terkirim => sent
        if (Schema::hasColumn('salary_slips', 'send_status')) {
            \DB::table('salary_slips')->whereNotNull('sent_at')->update(['send_status' => 'sent']);
        }
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $cols = [
                'batch_id',
                'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
                'tunjangan_lain', 'lembur', 'tambahan_gaji', 'ph_dibayar', 'refund_seragam',
                'jumlah_service_charge', 'total_penerimaan',
                'total_pot_absen', 'bpjs_ketenagakerjaan', 'bpjs_kesehatan', 'pinjaman',
                'pph21', 'potongan_seragam', 'koreksi', 'total_potongan',
                'hk_hari', 'alpha_hari', 'ijin_ap_hari', 'sakit_hari', 'cuti_hari',
                'sistem_pembayaran', 'no_rekening', 'nama_bank', 'atas_nama',
                'send_status', 'send_error', 'pdf_path', 'mail_account_id',
                'created_by', 'updated_by',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('salary_slips', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
