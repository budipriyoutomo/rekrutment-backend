<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak keputusan "Tolak" dari HR: alasan internal (tidak dikirim ke kandidat),
 * waktu penolakan, dan kapan email penolakan dimasukkan ke antrean (null = tidak
 * dikirim). Kolom biasa tanpa index/constraint supaya SQLite tidak membangun ulang
 * tabel (lihat migrasi vacancy_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('rejection_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'rejected_at', 'rejection_notified_at']);
        });
    }
};
