<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('application_experiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');

            $table->string('company_name')->nullable();
            $table->string('job_position')->nullable();

            $table->year('year_start')->nullable();
            $table->year('year_end')->nullable();

            $table->text('job_description')->nullable();

            $table->string('restaurant_industry')->nullable();
            $table->json('restaurant_type')->nullable();
            $table->string('position_category')->nullable();

            $table->json('responsibilities')->nullable();
            $table->json('pos_experience')->nullable();
            $table->json('pos_system')->nullable();
            $table->json('shifts')->nullable();

            $table->string('team_size')->nullable();
            $table->string('reason_for_leaving')->nullable();

            $table->timestamps();

            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->cascadeOnDelete();

            // 🔥 optional index untuk performa
            $table->index('application_id');
            $table->index('position_category');
            $table->index('restaurant_industry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_experiences');
    }
};