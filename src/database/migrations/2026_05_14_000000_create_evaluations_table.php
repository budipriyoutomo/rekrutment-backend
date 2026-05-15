<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('applicant_id');
            $table->string('applicant_name');
            $table->string('position')->nullable();
            $table->string('evaluator');
            $table->date('date')->nullable();
            $table->unsignedTinyInteger('communication_score');
            $table->unsignedTinyInteger('technical_score');
            $table->unsignedTinyInteger('experience_score');
            $table->unsignedTinyInteger('culture_fit_score');
            $table->enum('recommendation', ['strong_hire', 'hire', 'hold', 'reject'])->default('hold');
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('applicant_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->index('applicant_id');
            $table->index('recommendation');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
