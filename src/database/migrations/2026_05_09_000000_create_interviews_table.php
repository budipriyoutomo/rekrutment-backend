<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('applicant_id');

            $table->string('applicant_name');
            $table->string('position');

            $table->date('date');
            $table->time('time');
            $table->string('duration')->default('60 min');

            $table->enum('type', ['online', 'offline', 'technical_test'])->default('online');
            $table->json('interviewers');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');

            $table->string('room')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();

            $table->timestamps();

            $table->foreign('applicant_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->index('applicant_id');
            $table->index('date');
            $table->index('status');
            $table->index('type');
            $table->index(['date', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
