<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi relasi rekrutmen:
 *  - applications.vacancy_id → lamaran tahu lowongan mana yang dilamar
 *  - vacancies.job_request_id → lowongan tahu job request asalnya (1 job request = 1 lowongan)
 *  - vacancies.headcount → kuota yang dicari; null = tanpa batas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->foreignUuid('job_request_id')->nullable()->unique()
                ->constrained('job_requests')->nullOnDelete();
            $table->unsignedInteger('headcount')->nullable();
        });

        Schema::table('applications', function (Blueprint $table) {
            // SQLite (test) membangun ulang tabel saat menambah FK dan gagal
            // menyalin expression index applications_contact_email_unique, jadi
            // di SQLite cukup kolom + index tanpa constraint.
            if ($this->supportsForeignKeyAlter()) {
                $table->foreignUuid('vacancy_id')->nullable()
                    ->constrained('vacancies')->nullOnDelete();
            } else {
                $table->uuid('vacancy_id')->nullable();
            }
            $table->index('vacancy_id');
        });

        $this->backfillApplicationVacancies();
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if ($this->supportsForeignKeyAlter()) {
                $table->dropForeign(['vacancy_id']);
            }
            $table->dropIndex(['vacancy_id']);
            $table->dropColumn('vacancy_id');
        });

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_request_id');
            $table->dropColumn('headcount');
        });
    }

    private function supportsForeignKeyAlter(): bool
    {
        return DB::connection()->getDriverName() !== 'sqlite';
    }

    /**
     * Lamaran lama hanya menyimpan posisi sebagai teks (additional_info.positionApplied),
     * yang memakai nilai master data yang sama dengan vacancies.title. Cocokkan
     * berdasarkan title; bila ada beberapa lowongan dengan title sama, utamakan
     * yang lokasinya sama lalu yang terbaru. Yang tak cocok dibiarkan NULL.
     */
    private function backfillApplicationVacancies(): void
    {
        $vacanciesByTitle = DB::table('vacancies')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'location'])
            ->groupBy('title');

        if ($vacanciesByTitle->isEmpty()) {
            return;
        }

        DB::table('applications')
            ->whereNull('vacancy_id')
            ->chunkById(500, function ($apps) use ($vacanciesByTitle) {
                foreach ($apps as $app) {
                    $info = json_decode($app->additional_info ?? 'null', true) ?: [];
                    $candidates = $vacanciesByTitle->get($info['positionApplied'] ?? null);

                    if (! $candidates) {
                        continue;
                    }

                    $match = $candidates->firstWhere('location', $info['workLocation'] ?? null)
                        ?? $candidates->first();

                    DB::table('applications')->where('id', $app->id)->update(['vacancy_id' => $match->id]);
                }
            });
    }
};
