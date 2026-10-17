<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offer letter untuk kandidat di stage `offer`. Siklus status:
 * draft → sent → accepted | declined | expired, dan draft/sent → withdrawn.
 * PDF tidak disimpan: dirender ulang dari data offer saat dibutuhkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number')->unique();
            $table->foreignUuid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();

            $table->string('position');
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->unsignedBigInteger('base_salary');
            $table->json('allowances')->nullable();      // [{name, amount}]
            $table->date('start_date');
            $table->date('expires_at');                  // berlaku s.d. akhir hari ini
            $table->text('terms')->nullable();           // ketentuan tambahan di surat

            $table->string('status')->default('draft');
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('decline_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
