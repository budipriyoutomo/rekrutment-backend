<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-boarding: data & dokumen karyawan baru yang diisi kandidat lewat link
 * bertoken setelah offer diterima, lalu diverifikasi HR per dokumen.
 * Status: pending → submitted → (needs_revision ↔ submitted) → verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preboardings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignUuid('offer_id')->nullable()->constrained('offers')->nullOnDelete();

            $table->string('status')->default('pending');
            $table->string('token', 64)->unique();
            $table->timestamp('token_expires_at');

            $table->json('data')->nullable();       // rekening, NPWP, BPJS, kontak darurat, ukuran seragam
            $table->json('documents')->nullable();  // {type: {path, disk, file_name, mime_type, size, status, note, uploaded_at, reviewed_at}}

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preboardings');
    }
};
