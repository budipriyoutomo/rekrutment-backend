<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            // Peruntukan akun: recruitment (modul rekrutmen) | salary_slip (slip gaji).
            // Extensible untuk kategori lain di kemudian hari.
            $table->string('purpose')->default('salary_slip')->after('driver');
            $table->index(['purpose', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropIndex(['purpose', 'is_default']);
            $table->dropColumn('purpose');
        });
    }
};
