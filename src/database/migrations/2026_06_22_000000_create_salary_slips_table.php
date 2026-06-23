<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nik');
            $table->string('nama');
            $table->string('jabatan');
            $table->string('periode'); // format: YYYY-MM
            $table->string('cabang');
            $table->string('perusahaan');
            $table->bigInteger('take_home_pay');
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('periode');
            $table->index('nik');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
    }
};
