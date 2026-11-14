<?php

namespace App\Console\Commands;

use App\Domains\Application\Models\Application;
use App\Domains\Preboarding\Models\Preboarding;
use App\Domains\SalarySlip\Models\SalarySlip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

/**
 * Bersihkan file di storage (S3) yang tidak lagi direferensikan database —
 * sisa upload yang lamarannya gagal tersimpan, dokumen yang sudah diganti,
 * atau lamaran/pre-boarding yang sudah dihapus.
 *
 * Referensi dikumpulkan dari applications.documents (cv/foto/ktp/ijazah/
 * others/bundle) dan preboardings.documents. Default-nya dry-run; file baru
 * hanya dihapus dengan --delete. File yang lebih muda dari --min-age jam
 * dilewati agar upload yang baris DB-nya belum tersimpan tidak ikut terhapus.
 *
 * Tanpa --prefix, PDF payslip lokal (storage/app/payslips/{id}.pdf) yang
 * slip-nya sudah dihapus juga ikut diperiksa.
 */
class PruneOrphanStorageFiles extends Command
{
    /** Prefix yang ditulis aplikasi; di luar ini tidak pernah disentuh. */
    private const DEFAULT_PREFIXES = ['applications/', 'final_bundel/', 'preboarding/'];

    protected $signature = 'storage:prune-orphans
        {--disk= : Disk yang dipindai (default: filesystems.upload_disk)}
        {--prefix=* : Prefix yang dipindai (default: applications/, final_bundel/, preboarding/)}
        {--min-age=24 : Lewati file yang lebih muda dari N jam}
        {--limit=50 : Jumlah baris orphan yang ditampilkan}
        {--delete : Benar-benar hapus file (tanpa ini hanya dry-run)}
        {--force : Lewati konfirmasi saat --delete}';

    protected $description = 'Hapus file storage yang tidak direferensikan database (default dry-run)';

    public function handle(): int
    {
        $diskName = $this->option('disk') ?: config('filesystems.upload_disk', 's3');
        $prefixes = $this->option('prefix') ?: self::DEFAULT_PREFIXES;
        $cutoff = now()->subHours((int) $this->option('min-age'))->getTimestamp();
        $disk = Storage::disk($diskName);

        // PDF payslip disimpan lokal (storage/app/payslips), bukan di disk upload.
        $payslipsOk = $this->option('prefix') ? true : $this->prunePayslips($cutoff);

        $referenced = $this->referencedPaths();
        $this->info('Path direferensikan DB: '.count($referenced));

        $orphans = [];
        $orphanBytes = [];
        $scanned = 0;
        $skippedRecent = 0;

        foreach ($prefixes as $prefix) {
            $listing = $disk->getDriver()->listContents(rtrim($prefix, '/'), true);

            foreach ($listing as $item) {
                if (! $item instanceof FileAttributes) {
                    continue;
                }
                $scanned++;
                $path = ltrim($item->path(), '/');

                if (isset($referenced[$path])) {
                    continue;
                }
                if (($item->lastModified() ?? 0) > $cutoff) {
                    $skippedRecent++;

                    continue;
                }

                $orphans[$path] = [$path, $this->humanSize($item->fileSize() ?? 0), date('Y-m-d H:i', $item->lastModified() ?? 0)];
                $orphanBytes[$path] = $item->fileSize() ?? 0;
            }
        }

        $totalBytes = array_sum($orphanBytes);
        $this->line("Disk: {$diskName} | prefix: ".implode(', ', $prefixes));
        $this->line("Dipindai: {$scanned} file | dilewati (lebih baru dari {$this->option('min-age')} jam): {$skippedRecent}");

        if ($orphans === []) {
            $this->info('Tidak ada file orphan.');

            return $payslipsOk ? self::SUCCESS : self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $this->table(['Path', 'Ukuran', 'Terakhir diubah'], array_slice(array_values($orphans), 0, $limit));
        if (count($orphans) > $limit) {
            $this->line('… dan '.(count($orphans) - $limit).' file lainnya (pakai --limit untuk melihat semua).');
        }
        $this->warn(count($orphans).' file orphan, total '.$this->humanSize($totalBytes).'.');

        if (! $this->option('delete')) {
            $this->comment('Dry-run: tidak ada file yang dihapus. Jalankan ulang dengan --delete untuk menghapus.');

            return $payslipsOk ? self::SUCCESS : self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Hapus '.count($orphans)." file dari disk {$diskName}?")) {
            $this->comment('Dibatalkan.');

            return $payslipsOk ? self::SUCCESS : self::FAILURE;
        }

        $deleted = 0;
        foreach (array_chunk(array_keys($orphans), 100) as $chunk) {
            if ($disk->delete($chunk)) {
                $deleted += count($chunk);
            } else {
                $this->error('Gagal menghapus sebagian file: '.implode(', ', $chunk));
            }
        }
        $this->info("{$deleted} file dihapus.");

        return $deleted === count($orphans) && $payslipsOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * PDF payslip ({id}.pdf) yang slip-nya sudah tidak ada di salary_slips.
     * Mengikuti --min-age, --delete, dan --force yang sama.
     *
     * @return bool false bila ada file yang gagal dihapus
     */
    private function prunePayslips(int $cutoff): bool
    {
        $dir = storage_path('app/payslips');
        if (! is_dir($dir)) {
            return true;
        }

        $orphans = [];
        foreach (glob("{$dir}/*.pdf") ?: [] as $file) {
            if (filemtime($file) > $cutoff) {
                continue;
            }
            $orphans[basename($file, '.pdf')] = $file;
        }

        if ($orphans !== []) {
            $existing = SalarySlip::query()->whereIn('id', array_keys($orphans))->pluck('id')->all();
            $orphans = array_diff_key($orphans, array_flip($existing));
        }

        if ($orphans === []) {
            $this->info('Tidak ada PDF payslip orphan.');

            return true;
        }

        $this->table(['PDF payslip orphan (lokal)'], array_map(
            fn (string $id) => ["payslips/{$id}.pdf"],
            array_slice(array_keys($orphans), 0, (int) $this->option('limit')),
        ));
        $this->warn(count($orphans).' PDF payslip orphan.');

        if (! $this->option('delete')) {
            return true;
        }

        if (! $this->option('force') && ! $this->confirm('Hapus '.count($orphans).' PDF payslip orphan?')) {
            return true;
        }

        $failed = array_filter($orphans, fn (string $file) => ! @unlink($file));
        $this->info((count($orphans) - count($failed)).' PDF payslip dihapus.');

        return $failed === [];
    }

    /**
     * Semua path dokumen yang tercatat di DB, sebagai set [path => true].
     * Disk diabaikan (sengaja konservatif): path yang direferensikan di disk
     * mana pun tidak akan dihapus.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach ([Application::class, Preboarding::class] as $model) {
            $model::query()->select(['id', 'documents'])->lazyById(500)->each(function ($row) use (&$paths) {
                foreach ((array) ($row->documents ?? []) as $document) {
                    // Data lama menyimpan dokumen sebagai string path saja
                    $path = is_array($document) ? ($document['path'] ?? null) : $document;
                    if (! is_string($path) || $path === '') {
                        continue;
                    }
                    foreach ($this->pathVariants($path) as $variant) {
                        $paths[$variant] = true;
                    }
                }
            });
        }

        return $paths;
    }

    /**
     * Path bisa tersimpan sebagai key atau URL penuh (path-style: /bucket/key),
     * jadi URL diturunkan ke key dengan dan tanpa segmen pertama.
     *
     * @return list<string>
     */
    private function pathVariants(string $path): array
    {
        if (! preg_match('#^https?://#i', $path)) {
            return [ltrim($path, '/')];
        }

        $urlPath = ltrim(rawurldecode(parse_url($path, PHP_URL_PATH) ?? ''), '/');
        $withoutBucket = str_contains($urlPath, '/') ? substr($urlPath, strpos($urlPath, '/') + 1) : $urlPath;

        return array_values(array_unique([$urlPath, $withoutBucket]));
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }
}
