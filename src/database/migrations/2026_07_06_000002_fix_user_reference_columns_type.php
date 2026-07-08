<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan tipe kolom referensi-user (created_by/updated_by/uploaded_by)
 * dengan tipe users.id yang sebenarnya (bigint), bukan uuid. Kolom-kolom ini
 * sebelumnya uuid tetapi selalu null (userstamps tidak pernah aktif karena
 * request belum terautentikasi), sehingga aman di-drop & dibuat ulang.
 */
return new class extends Migration
{
    /** tabel => daftar kolom user-reference */
    private array $targets = [
        'import_batches' => ['uploaded_by'],
        'mail_accounts'  => ['created_by', 'updated_by'],
        'salary_slips'   => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->dropColumn($column);
                    });
                }

                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->unsignedBigInteger($column)->nullable();
                });
            }
        }

        // Kembalikan index pada import_batches.uploaded_by
        if (Schema::hasTable('import_batches') && Schema::hasColumn('import_batches', 'uploaded_by')) {
            Schema::table('import_batches', function (Blueprint $t) {
                $t->index('uploaded_by');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $t) use ($column) {
                        $t->dropColumn($column);
                    });
                }

                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->uuid($column)->nullable();
                });
            }
        }
    }
};
