<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');

            $table->text('question');
            // Pilihan jawaban: [{"key": "A", "text": "..."}, {"key": "B", "text": "..."}]
            $table->json('options');
            // Key opsi yang benar, mis. "A". Tidak pernah dikirim ke kandidat.
            $table->string('correct_answer', 8);
            // Bobot nilai soal; total bobot per paket jadi penyebut saat auto-grade.
            $table->unsignedSmallInteger('score')->default(1);
            $table->unsignedSmallInteger('order')->default(0);

            $table->timestamps();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('assessments')
                ->cascadeOnDelete();

            $table->index(['assessment_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_questions');
    }
};
