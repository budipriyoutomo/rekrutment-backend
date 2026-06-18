<?php

namespace App\Domains\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domains\Application\Services\ApplicationDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

class ProcessApplicationBundlingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $applicationId;

    public function __construct($applicationId)
    {
        $this->applicationId = $applicationId;
    }

    public function handle(ApplicationDocumentService $documentService): void
    {
        // Ambil data berdasarkan ID UID dari Postgres
        $application = DB::table('applications')->find($this->applicationId);
        
        if (!$application) return;

        // 1. Inisialisasi folder temporary lokal
        $tempDir = storage_path('app/temp_processing');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $uniqueId = Str::random(10);
        $generatedDocxPath = $tempDir . '/cv_' . $this->applicationId . '_' . $uniqueId . '.docx';
        $generatedPdfPath = $tempDir . '/cv_' . $this->applicationId . '_' . $uniqueId . '.pdf';

        // =========================================================================
        // TAHAP 1: GENERATE WORD DARI DATA DATABASE
        // =========================================================================
        $documentService->generateApplicationDocx($application, $generatedDocxPath);


        // =========================================================================
        // TAHAP 2: KONVERSI HASIL GENERATE KE PDF (VIA LIBREOFFICE DOCKER)
        // =========================================================================
        $this->convertToPdf($generatedDocxPath, $tempDir);


        // =========================================================================
        // TAHAP 3: DOWNLOAD LAMPIRAN DARI S3 BERDASARKAN DATA JSONB DOKUMEN
        // =========================================================================
        $documentsData = is_string($application->documents) 
            ? json_decode($application->documents, true) 
            : (array) $application->documents;

        $s3KeyAttachment = $documentsData['ijazah']['path'] ?? $documentsData['ktp']['path'] ?? null;

        // Siapkan array untuk menampung file-file PDF yang akan digabungkan
        $pdfFilesToMerge = [];
        
        // Masukkan PDF utama (Biodata dari database) ke urutan pertama
        if (file_exists($generatedPdfPath)) {
            $pdfFilesToMerge[] = $generatedPdfPath;
        }

        $localS3Path = null;
        $finalAttachmentPdfPath = null;

        if ($s3KeyAttachment) {
            $s3Disk = Storage::disk('s3');
            
            if ($s3Disk->exists($s3KeyAttachment)) {
                $s3Filename = basename($s3KeyAttachment);
                $s3Extension = strtolower(pathinfo($s3Filename, PATHINFO_EXTENSION));
                
                $localS3Path = $tempDir . "/s3_input_{$uniqueId}." . $s3Extension;
                
                // Ambil berkas lampiran dari S3
                file_put_contents($localS3Path, $s3Disk->get($s3KeyAttachment));

                if ($s3Extension === 'pdf') {
                    $finalAttachmentPdfPath = $localS3Path;
                } else {
                    // Konversi gambar/word ke PDF menggunakan LibreOffice headless
                    $this->convertToPdf($localS3Path, $tempDir);
                    $finalAttachmentPdfPath = $tempDir . "/s3_input_{$uniqueId}.pdf";
                }

                if (file_exists($finalAttachmentPdfPath)) {
                    $pdfFilesToMerge[] = $finalAttachmentPdfPath;
                }
            }
        }

        // =========================================================================
        // TAHAP 4: MERGE KEDUA PDF MENGGUNAKAN NATIVE FPDI (SANGAT STABIL)
        // =========================================================================
        $outputFinalPdf = $tempDir . "/bundel_rekrutmen_{$this->applicationId}_{$uniqueId}.pdf";
        
        try {
            $this->mergePdfFiles($pdfFilesToMerge, $outputFinalPdf);
        } catch (\Exception $e) {
            // Bersihkan file sisa jika proses merge internal gagal
            $this->cleanup([$generatedDocxPath, $generatedPdfPath, $localS3Path, $finalAttachmentPdfPath]);
            throw new \Exception("Gagal menggabungkan halaman PDF via FPDI: " . $e->getMessage());
        }


        // =========================================================================
        // TAHAP 5: UPLOAD KEMBALI BUNDEL FINAL KE S3 & CLEANUP
        // =========================================================================
        $s3FinalKey = "final_bundel/rekrutmen_{$this->applicationId}.pdf";
        $bundleDisk = config('filesystems.bundle_disk', 's3');

        try {
            $uploaded = Storage::disk($bundleDisk)->put($s3FinalKey, file_get_contents($outputFinalPdf), [
                'visibility' => 'public',
            ]);

            if (!$uploaded) {
                throw new \RuntimeException("Upload bundel final ke disk {$bundleDisk} gagal.");
            }

            Log::info('Application bundle uploaded', [
                'application_id' => $this->applicationId,
                'disk' => $bundleDisk,
                'path' => $s3FinalKey,
            ]);
        } finally {
            // Bersihkan berkas lokal, termasuk jika upload ke S3 gagal.
            $this->cleanup([
                $generatedDocxPath,
                $generatedPdfPath,
                $localS3Path,
                $finalAttachmentPdfPath,
                $outputFinalPdf,
            ]);
        }
    }

    /**
     * Fungsi merger internal menggunakan library native FPDI
     */
    private function mergePdfFiles(array $files, string $outputPath): void
    {
        // Menggunakan class FPDI dari SetaSign
        $pdf = new \setasign\Fpdi\Fpdi();

        foreach ($files as $file) {
            // Hitung total halaman dari masing-masing file PDF secara dinamis
            $pageCount = $pdf->setSourceFile($file);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // Import halaman
                $templateId = $pdf->importPage($pageNo);
                // Dapatkan ukuran orientasi halaman asli (Potrait/Landscape)
                $size = $pdf->getTemplateSize($templateId);

                // Tambahkan halaman baru dengan ukuran orientasi yang sesuai
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                // Gambar ulang template PDF tersebut ke halaman baru
                $pdf->useTemplate($templateId);
            }
        }

        // Simpan hasil bundel final ke local path temporary sebelum di-upload ke S3
        $pdf->Output('F', $outputPath);
    }

    private function convertToPdf(string $filePath, string $outputDir): void
    {
        $binary = $this->resolveLibreOfficeBinary();

        $process = new Process([$binary, '--headless', '--convert-to', 'pdf', '--outdir', $outputDir, $filePath]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Application bundle PDF conversion failed', [
                'binary' => $binary,
                'file' => $filePath,
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            throw new \Exception("LibreOffice Gagal: " . $process->getErrorOutput());
        }
    }

    private function resolveLibreOfficeBinary(): string
    {
        $configuredBinary = env('LIBREOFFICE_BINARY');
        if ($configuredBinary && is_executable($configuredBinary)) {
            return $configuredBinary;
        }

        $finder = new ExecutableFinder();
        foreach (['libreoffice', 'soffice'] as $name) {
            $binary = $finder->find($name);
            if ($binary) {
                return $binary;
            }
        }

        foreach ([
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
            '/opt/homebrew/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new \Exception('LibreOffice tidak ditemukan di server. Install LibreOffice atau set LIBREOFFICE_BINARY.');
    }

    private function cleanup(array $files): void
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
