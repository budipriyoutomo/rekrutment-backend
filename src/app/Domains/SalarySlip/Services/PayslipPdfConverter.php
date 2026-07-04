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

        $binary     = $this->resolveBinary();
        $profileDir = storage_path('app/libreoffice-profile');
        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0777, true);
        }

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

        if (! $process->isSuccessful()) {
            Log::error('PAYSLIP PDF CONVERSION ERROR', [
                'binary' => $binary,
                'error'  => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            throw new \RuntimeException('Gagal mengonversi slip gaji ke PDF.');
        }

        $pdfPath = $outputDir . DIRECTORY_SEPARATOR
            . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';

        if (! is_file($pdfPath)) {
            throw new \RuntimeException('File PDF hasil konversi tidak ditemukan.');
        }

        return $pdfPath;
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
