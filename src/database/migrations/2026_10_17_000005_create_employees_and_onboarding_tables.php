<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Karyawan (hasil konversi pre-boarding terverifikasi) dan checklist onboarding:
 *  - onboarding_task_templates: master item checklist per departemen (null = semua)
 *  - onboarding_tasks: task per karyawan, due date dihitung dari start_date + offset
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_number')->unique();   // NIK karyawan
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('preboarding_id')->nullable()->constrained('preboardings')->nullOnDelete();
            $table->foreignUuid('offer_id')->nullable()->constrained('offers')->nullOnDelete();

            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('position');
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->date('start_date');
            $table->json('personal_data')->nullable();      // salinan data pre-boarding (rekening, NPWP, BPJS, kontak darurat)

            $table->string('status')->default('onboarding'); // onboarding | active
            $table->timestamp('welcome_sent_at')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('start_date');
        });

        Schema::create('onboarding_task_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department')->nullable();       // null = berlaku untuk semua departemen
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('pic')->nullable();              // penanggung jawab, mis. "IT", "HR", "Atasan"
            $table->integer('offset_days')->default(0);     // relatif ke start_date; negatif = sebelum hari pertama
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('onboarding_task_templates')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('pic')->nullable();
            $table->date('due_date');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'completed_at']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_task_templates');
        Schema::dropIfExists('employees');
    }
};
