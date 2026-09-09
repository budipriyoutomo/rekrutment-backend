<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_slips', 'keterangan_potongan')) {
                // Keterangan/catatan bebas untuk sisi potongan — pasangan dari
                // keterangan_tambahan_gaji di sisi penerimaan.
                $table->string('keterangan_potongan')->nullable()->after('total_potongan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_slips', function (Blueprint $table) {
            if (Schema::hasColumn('salary_slips', 'keterangan_potongan')) {
                $table->dropColumn('keterangan_potongan');
            }
        });
    }
};
