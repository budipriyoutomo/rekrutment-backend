<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('send_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('salary_slip_id');
            $table->uuid('mail_account_id')->nullable();
            $table->uuid('job_batch_id')->nullable(); // grouping per klik "Kirim Terpilih"
            $table->string('status')->default('queued'); // queued | processing | success | failed
            $table->unsignedInteger('attempt')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('salary_slip_id');
            $table->index('job_batch_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('send_jobs');
    }
};
