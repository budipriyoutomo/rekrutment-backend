<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah snapshot soal ke tiap penugasan tes.
 *
 * Saat paket dikirim ke kandidat, soal (beserta kunci & bobot) dibekukan ke
 * kolom ini. Auto-grade dan halaman pengerjaan membaca dari sini, bukan dari
 * paket "hidup", sehingga HR bebas mengedit paket tanpa merusak hasil lama.
 *
 * Bentuk: array soal beku
 *   [{"id","question","options","correct_answer","score","order"}, ...]
 * `id` dipertahankan agar answers kandidat (berkunci question_id) tetap memetakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_assignments', function (Blueprint $table) {
            $table->json('questions_snapshot')->nullable()->after('answers');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_assignments', function (Blueprint $table) {
            $table->dropColumn('questions_snapshot');
        });
    }
};
