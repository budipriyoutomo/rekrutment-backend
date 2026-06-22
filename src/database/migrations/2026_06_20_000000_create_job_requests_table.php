<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type')->default('full-time');
            $table->unsignedInteger('headcount')->default(1);
            $table->string('salary_range')->nullable();
            $table->date('needed_by')->nullable();
            $table->text('justification')->nullable();
            $table->json('requirements')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requests');
    }
};
