<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_slips', 'keterangan_tambahan_gaji')) {
                // Keterangan/catatan bebas untuk komponen Tambahan Gaji.
                $table->string('keterangan_tambahan_gaji')->nullable()->after('tambahan_gaji');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            if (Schema::hasColumn('salary_slips', 'keterangan_tambahan_gaji')) {
                $table->dropColumn('keterangan_tambahan_gaji');
            }
        });
    }
};
