<?php

namespace App\Domains\SalarySlip\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Konversi dokumen (docx) -> PDF via LibreOffice headless, khusus modul payslip.
 *
 * Sengaja berdiri sendiri (tidak memakai ulang FileUploadService) agar perubahan
 * di modul payslip tidak berisiko memengaruhi alur dokumen aplikasi yang sudah ada.
 * Pola resolve binary mengikuti konvensi project.
 */
class PayslipPdfConverter
{
    /**
     * Konversi $sourcePath (docx) menjadi PDF di $outputDir.
     * Mengembalikan path absolut file PDF hasil konversi.
     */
    public function toPdf(string $sourcePath, string $outputDir): string
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        $binary = $this->resolveBinary();

        // Profile UserInstallation UNIK per konversi. LibreOffice headless cuma
        // boleh dipakai satu proses per profile; kalau profile dipakai bareng
        // (mis. konversi CV/bundling jalan barengan lewat queue) proses kedua
        // exit 0 tapi TIDAK menghasilkan file -> "File PDF ... tidak ditemukan".
        // Profile ditaruh di dalam outputDir yang sudah unik per run, lalu dibersihkan.
        $profileDir = $outputDir . DIRECTORY_SEPARATOR . 'lo-profile';
        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0777, true);
        }

        try {
            $process = new Process([
                $binary,
                '-env:UserInstallation=file://' . $profileDir,
                '--headless',
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDir,
                $sourcePath,
            ]);
            $process->setTimeout(120);
            $process->run();

            $pdfPath = $outputDir . DIRECTORY_SEPARATOR
                . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';

            // LibreOffice sering exit 0 walau gagal menulis file, jadi keberadaan
            // file PDF adalah sumber kebenaran, bukan cuma exit code.
            if (! $process->isSuccessful() || ! is_file($pdfPath)) {
                Log::error('PAYSLIP PDF CONVERSION ERROR', [
                    'binary'      => $binary,
                    'source'      => $sourcePath,
                    'expected'    => $pdfPath,
                    'exit_code'   => $process->getExitCode(),
                    'outdir_list' => glob($outputDir . DIRECTORY_SEPARATOR . '*') ?: [],
                    'error'       => $process->getErrorOutput(),
                    'output'      => $process->getOutput(),
                ]);

                throw new \RuntimeException(
                    $process->isSuccessful()
                        ? 'File PDF hasil konversi tidak ditemukan.'
                        : 'Gagal mengonversi slip gaji ke PDF.'
                );
            }

            return $pdfPath;
        } finally {
            $this->removeDir($profileDir);
        }
    }

    /** Hapus direktori profile LibreOffice sementara beserta isinya (rekursif). */
    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    public function resolveBinary(): string
    {
        $configured = env('LIBREOFFICE_BINARY');
        if ($configured && is_executable($configured)) {
            return $configured;
        }

        $finder = new ExecutableFinder();
        foreach (['libreoffice', 'soffice'] as $name) {
            if ($binary = $finder->find($name)) {
                return $binary;
            }
        }

        foreach ([
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
            '/usr/local/bin/soffice',
            '/opt/homebrew/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'LibreOffice tidak ditemukan. Install LibreOffice atau set LIBREOFFICE_BINARY.'
        );
    }

    /** Untuk test: apakah converter bisa jalan di environment ini. */
    public function isAvailable(): bool
    {
        try {
            $this->resolveBinary();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
