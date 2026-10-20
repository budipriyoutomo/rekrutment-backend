<?php

namespace App\Console\Commands;

use App\Domains\Application\Models\Application;
use Illuminate\Console\Command;

/**
 * Daftar lamaran yang belum punya CV dan/atau foto — sisa alur Quick Apply
 * lama yang menyimpan lamaran dulu lalu mengunggah file di request terpisah
 * (upload gagal → lamaran tertinggal tanpa dokumen). Hanya membaca, tidak
 * mengubah data apa pun.
 */
class ListApplicationsMissingDocuments extends Command
{
    protected $signature = 'applications:missing-documents
        {--quick-only : Hanya lamaran dari Quick Apply (/apply)}
        {--type=* : Dokumen yang dicek (default: cv dan foto)}';

    protected $description = 'Tampilkan lamaran yang belum memiliki CV/foto';

    public function handle(): int
    {
        $types = $this->option('type') ?: ['cv', 'foto'];
        $rows = [];

        Application::query()
            ->select(['id', 'personal_info', 'contact_info', 'additional_info', 'documents', 'stage', 'created_at'])
            ->orderBy('created_at')
            ->chunk(500, function ($apps) use ($types, &$rows) {
                foreach ($apps as $app) {
                    $isQuick = (bool) ($app->additional_info['quickApply'] ?? false);
                    if ($this->option('quick-only') && ! $isQuick) {
                        continue;
                    }

                    $missing = array_values(array_filter($types, fn ($t) => ! $app->hasDocument($t)));
                    if ($missing === []) {
                        continue;
                    }

                    $rows[] = [
                        $app->id,
                        $app->personal_info['fullName'] ?? '-',
                        $app->contact_info['email'] ?? '-',
                        $isQuick ? 'Quick Apply' : 'Form lengkap',
                        $app->stage,
                        implode(', ', $missing),
                        optional($app->created_at)->format('Y-m-d H:i'),
                    ];
                }
            });

        if ($rows === []) {
            $this->info('Semua lamaran sudah memiliki dokumen yang dicek.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Nama', 'Email', 'Sumber', 'Stage', 'Belum ada', 'Dibuat'], $rows);
        $this->warn(count($rows).' lamaran belum lengkap dokumennya.');

        return self::SUCCESS;
    }
}
