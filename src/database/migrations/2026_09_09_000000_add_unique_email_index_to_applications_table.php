<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pertahanan lapis kedua terhadap lamaran ganda: partial unique index pada
 * email pelamar (contact_info->email), case-insensitive. Pengecekan utama tetap
 * di ApplicationService::createFull; index ini menutup celah race condition dua
 * submit paralel.
 *
 * Bila database sudah terlanjur berisi email ganda, index tidak dibuat (akan
 * gagal) — migrasi hanya mencatat peringatan. Bersihkan duplikat lalu jalankan
 * `php artisan migrate` lagi.
 */
return new class extends Migration
{
    private const INDEX = 'applications_contact_email_unique';

    public function up(): void
    {
        $email = $this->emailExpression();

        $duplicates = DB::table('applications')
            ->selectRaw("lower(trim($email)) as email, count(*) as total")
            ->whereRaw("$email is not null and trim($email) <> ''")
            ->groupByRaw("lower(trim($email))")
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning(
                'Migrasi ' . self::INDEX . ' dilewati: masih ada email pelamar ganda. ' .
                'Bersihkan duplikat lalu jalankan migrate ulang.',
                ['emails' => $duplicates->pluck('email')->all()]
            );

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX ' . self::INDEX . ' ON applications ' .
            "(lower(trim($email))) WHERE $email IS NOT NULL AND trim($email) <> ''"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
    }

    /** Ekspresi SQL untuk mengambil contact_info->email, per driver DB. */
    private function emailExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "contact_info->>'email'"
            : "json_extract(contact_info, '\$.email')";
    }
};
