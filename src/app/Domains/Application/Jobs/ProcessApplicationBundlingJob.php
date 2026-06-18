<?php

namespace App\Domains\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Services\ApplicationDocumentService;
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
        $application = Application::with(['educations', 'experiences', 'certifications'])->find($this->applicationId);
        
        if (!$application) return;

        // 1. Inisialisasi folder temporary lokal
        $tempDir = storage_path('app/temp_processing');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $uniqueId = Str::random(10);
        $generatedDocxPath = $tempDir . '/candidate_' . $this->applicationId . '_' . $uniqueId . '.docx';
        $generatedPdfPath = $tempDir . '/candidate_' . $this->applicationId . '_' . $uniqueId . '.pdf';
        $downloadedFiles = [];

        try {
            // =========================================================================
            // TAHAP 1: GENERATE BIODATA KANDIDAT DARI DATABASE
            // =========================================================================
            $documentService->generateApplicationDocx($application, $generatedDocxPath);


            // =========================================================================
            // TAHAP 2: KONVERSI BIODATA KE PDF
            // =========================================================================
            $this->convertToPdf($generatedDocxPath, $tempDir);

            if (!file_exists($generatedPdfPath)) {
                throw new \RuntimeException('PDF biodata kandidat tidak berhasil dibuat.');
            }

            // =========================================================================
            // TAHAP 3: DOWNLOAD SEMUA DOKUMEN KANDIDAT
            // =========================================================================
            $documentsData = is_string($application->documents)
                ? json_decode($application->documents, true)
                : (array) ($application->documents ?? []);

            $filesToMerge = [[
                'type' => 'pdf',
                'path' => $generatedPdfPath,
                'label' => 'Biodata Kandidat',
            ]];

            foreach (['cv' => 'CV / Resume', 'foto' => 'Foto Diri', 'ktp' => 'Foto KTP', 'ijazah' => 'Scan Ijazah'] as $key => $label) {
                $document = $documentsData[$key] ?? null;
                $sourcePath = is_array($document) ? ($document['path'] ?? null) : $document;

                if (!$sourcePath) {
                    continue;
                }

                $localPath = $this->downloadDocument($sourcePath, $tempDir, "{$key}_{$uniqueId}");
                if (!$localPath) {
                    Log::warning('Application bundle document missing', [
                        'application_id' => $this->applicationId,
                        'document_type' => $key,
                        'path' => $sourcePath,
                    ]);
                    continue;
                }

                $downloadedFiles[] = $localPath;
                $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

                if ($extension === 'pdf') {
                    $filesToMerge[] = [
                        'type' => 'pdf',
                        'path' => $localPath,
                        'label' => $label,
                    ];
                    continue;
                }

                if ($this->isImageExtension($extension)) {
                    $filesToMerge[] = [
                        'type' => 'image',
                        'path' => $localPath,
                        'label' => $label,
                    ];
                    continue;
                }

                if (in_array($extension, ['doc', 'docx'], true)) {
                    $this->convertToPdf($localPath, $tempDir);
                    $convertedPath = $tempDir . '/' . pathinfo($localPath, PATHINFO_FILENAME) . '.pdf';
                    if (file_exists($convertedPath)) {
                        $downloadedFiles[] = $convertedPath;
                        $filesToMerge[] = [
                            'type' => 'pdf',
                            'path' => $convertedPath,
                            'label' => $label,
                        ];
                    }
                }
            }

            // =========================================================================
            // TAHAP 4: MERGE BIODATA + CV + FOTO + KTP + IJAZAH
            // =========================================================================
            $outputFinalPdf = $tempDir . "/bundel_rekrutmen_{$this->applicationId}_{$uniqueId}.pdf";
            $this->mergeDocuments($filesToMerge, $outputFinalPdf);

            // =========================================================================
            // TAHAP 5: UPLOAD BUNDEL FINAL DAN SIMPAN METADATA PREVIEW
            // =========================================================================
            $s3FinalKey = "final_bundel/rekrutmen_{$this->applicationId}.pdf";
            $bundleDisk = config('filesystems.bundle_disk', 's3');
            $uploaded = Storage::disk($bundleDisk)->put($s3FinalKey, file_get_contents($outputFinalPdf), [
                'visibility' => 'public',
            ]);

            if (!$uploaded) {
                throw new \RuntimeException("Upload bundel final ke disk {$bundleDisk} gagal.");
            }

            $bundleUrl = $this->url($bundleDisk, $s3FinalKey);
            $documentsData['bundle'] = [
                'status' => 'ready',
                'path' => $s3FinalKey,
                'file_url' => $bundleUrl,
                'file_name' => "bundel_rekrutmen_{$this->applicationId}.pdf",
                'mime_type' => 'application/pdf',
                'size' => filesize($outputFinalPdf),
                'message' => 'Bundle document siap dipreview.',
                'generated_at' => now()->toISOString(),
            ];

            $application->update([
                'documents' => $documentsData,
            ]);

            Log::info('Application bundle uploaded', [
                'application_id' => $this->applicationId,
                'disk' => $bundleDisk,
                'path' => $s3FinalKey,
            ]);
        } catch (\Throwable $e) {
            $this->markBundleFailed($application, $e->getMessage());
            throw $e;
        } finally {
            $this->cleanup([
                $generatedDocxPath,
                $generatedPdfPath,
                $outputFinalPdf ?? null,
                ...$downloadedFiles,
            ]);
        }
    }

    private function mergeDocuments(array $files, string $outputPath): void
    {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->SetAutoPageBreak(true, 12);

        foreach ($files as $fileData) {
            if (($fileData['type'] ?? null) === 'image') {
                $this->addImagePage($pdf, $fileData['path'], $fileData['label'] ?? 'Dokumen');
                continue;
            }

            $pageCount = $pdf->setSourceFile($fileData['path']);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }
        }

        $pdf->Output('F', $outputPath);
    }

    private function addImagePage(\setasign\Fpdi\Fpdi $pdf, string $imagePath, string $label): void
    {
        $size = getimagesize($imagePath);
        if (!$size) {
            return;
        }

        [$imageWidth, $imageHeight] = $size;
        $pageWidth = 210;
        $pageHeight = 297;
        $margin = 15;
        $titleHeight = 12;
        $maxWidth = $pageWidth - ($margin * 2);
        $maxHeight = $pageHeight - ($margin * 2) - $titleHeight;
        $ratio = min($maxWidth / $imageWidth, $maxHeight / $imageHeight);
        $displayWidth = $imageWidth * $ratio;
        $displayHeight = $imageHeight * $ratio;
        $x = ($pageWidth - $displayWidth) / 2;
        $y = $margin + $titleHeight;

        $pdf->AddPage('P', [$pageWidth, $pageHeight]);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, $label, 0, 1, 'C');
        $pdf->Image($imagePath, $x, $y, $displayWidth, $displayHeight);
    }

    private function downloadDocument(string $path, string $tempDir, string $baseName): ?string
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION)) ?: 'bin';
        $localPath = "{$tempDir}/{$baseName}.{$extension}";
        $diskNames = array_values(array_unique([
            config('filesystems.upload_disk', 's3'),
            config('filesystems.bundle_disk', 's3'),
            's3',
            'public',
            'local',
        ]));

        foreach ($diskNames as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if (!$disk->exists($path)) {
                    continue;
                }

                file_put_contents($localPath, $disk->get($path));
                return $localPath;
            } catch (\Throwable $e) {
                Log::warning('Application bundle document download failed on disk', [
                    'application_id' => $this->applicationId,
                    'disk' => $diskName,
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function isImageExtension(string $extension): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png'], true);
    }

    private function convertToPdf(string $filePath, string $outputDir): void
    {
        $binary = $this->resolveLibreOfficeBinary();
        $profileDir = storage_path('app/libreoffice-profile');
        if (!is_dir($profileDir)) {
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
            $filePath,
        ]);
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

    private function url(string $diskName, string $path): string
    {
        try {
            return Storage::disk($diskName)->url($path);
        } catch (\Throwable $e) {
            $baseUrl = config("filesystems.disks.{$diskName}.url", '');
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }
    }

    private function markBundleFailed(Application $application, string $message): void
    {
        $documents = $application->documents ?? [];
        $documents['bundle'] = [
            'status' => 'failed',
            'path' => null,
            'file_url' => null,
            'file_name' => "bundle_{$this->applicationId}.pdf",
            'mime_type' => 'application/pdf',
            'size' => null,
            'message' => $message,
            'generated_at' => null,
        ];

        $application->update([
            'documents' => $documents,
        ]);
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
