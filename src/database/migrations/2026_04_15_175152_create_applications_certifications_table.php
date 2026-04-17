<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_certifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');

            $table->string('course_name')->nullable();
            $table->string('organization')->nullable();
            $table->year('year')->nullable();
            $table->string('duration')->nullable();

            $table->timestamps();

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->index('application_id');
            $table->index('course_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_certifications');
    }
};