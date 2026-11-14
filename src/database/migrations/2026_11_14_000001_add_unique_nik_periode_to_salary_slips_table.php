<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu karyawan (NIK) hanya boleh punya satu slip per periode. Sebelumnya hanya
 * dicegah di aplikasi sehingga import paralel bisa menghasilkan slip ganda.
 *
 * Duplikat yang sudah ada TIDAK dihapus otomatis — migrasi berhenti dengan daftar
 * kombinasi yang bentrok agar HR memutuskan slip mana yang dipertahankan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('salary_slips')
            ->select('nik', 'periode', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('nik', 'periode')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('periode')
            ->orderBy('nik')
            ->limit(20)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates
                ->map(fn ($d) => "NIK {$d->nik} periode {$d->periode} ({$d->jumlah} slip)")
                ->implode('; ');

            throw new RuntimeException(
                'Tidak bisa menambah unique (nik, periode): masih ada slip ganda. '
                .'Hapus duplikatnya lewat menu Salary Slips lalu jalankan migrasi lagi. '
                ."Contoh: {$list}"
            );
        }

        Schema::table('salary_slips', function (Blueprint $table) {
            $table->unique(['nik', 'periode'], 'salary_slips_nik_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            $table->dropUnique('salary_slips_nik_periode_unique');
        });
    }
};
