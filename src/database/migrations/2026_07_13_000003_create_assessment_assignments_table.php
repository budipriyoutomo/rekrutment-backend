<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->uuid('assessment_id');

            // Token akses publik untuk kandidat (tanpa login), pola sama
            // dengan profile_completion_tokens.
            $table->string('token', 64)->unique();
            $table->enum('status', ['sent', 'in_progress', 'graded'])->default('sent');

            // Jawaban kandidat: {"<question_id>": "A", ...}
            $table->json('answers')->nullable();
            // Skor akhir dalam persen (0-100), diisi saat auto-grade.
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('passed')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            // Sengaja restrict: paket tes yang sudah pernah dikerjakan tidak boleh
            // dihapus agar hasil kandidat tidak ikut hilang. Pakai is_active untuk
            // menonaktifkan paket lama.
            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->restrictOnDelete();

            $table->index(['token', 'expires_at']);
            $table->index('application_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_assignments');
    }
};
