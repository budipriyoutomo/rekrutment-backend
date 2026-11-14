<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

/**
 * Migrasi satu kali: ubah visibility dokumen pelamar lama (diunggah saat masih
 * `public`) menjadi `private`, sehingga URL S3 permanennya tidak bisa dibuka lagi.
 *
 * Menyisir applications/ (cv, foto, ktp, ijazah, others) di upload_disk dan
 * final_bundel/ di bundle_disk. Default dry-run; perubahan hanya dengan --apply.
 */
class PrivatizeApplicantDocuments extends Command
{
    protected $signature = 'storage:privatize-applicant-documents
        {--apply : Ubah visibility ke private (tanpa ini hanya preview)}';

    protected $description = 'Jadikan dokumen pelamar lama private (default dry-run)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $targets = [
            [config('filesystems.upload_disk', 's3'), 'applications'],
            [config('filesystems.bundle_disk', 's3'), 'final_bundel'],
        ];

        $public = 0;
        $changed = 0;
        $failed = 0;

        foreach ($targets as [$diskName, $prefix]) {
            $disk = Storage::disk($diskName);

            foreach ($disk->getDriver()->listContents($prefix, true) as $item) {
                if (! $item instanceof FileAttributes) {
                    continue;
                }

                $path = ltrim($item->path(), '/');

                try {
                    if ($disk->getVisibility($path) !== 'public') {
                        continue;
                    }

                    $public++;
                    $this->line("{$diskName}: {$path}");

                    if ($apply) {
                        $disk->setVisibility($path, 'private');
                        $changed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("Gagal memproses {$diskName}: {$path} — {$e->getMessage()}");
                }
            }
        }

        if ($apply) {
            $this->info("Selesai: {$changed} file dijadikan private, {$failed} gagal.");
        } else {
            $this->info("Dry-run: {$public} file masih public. Jalankan dengan --apply untuk mengubahnya.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
