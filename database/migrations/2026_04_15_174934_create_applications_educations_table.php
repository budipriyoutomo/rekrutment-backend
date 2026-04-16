<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_educations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');

            $table->string('level')->nullable(); // SD, SMP, SMA, Universitas
            $table->string('school_name')->nullable();
            $table->string('city')->nullable();

            $table->year('year_start')->nullable();
            $table->year('year_end')->nullable();

            $table->string('major')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->string('certificate')->nullable();

            $table->timestamps();

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            $table->index('application_id');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_educations');
    }
};