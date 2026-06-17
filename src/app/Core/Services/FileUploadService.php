<?php

namespace App\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;


class FileUploadService
{
    protected string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.default', 's3');
    }

    /**
     * Upload single file
     */
    public function upload(UploadedFile $file, string $directory): array
    {
        $filename = $this->generateFilename($file);
        /*
        $path = Storage::disk($this->disk)->putFileAs(
            $directory,
            $file,
            $filename,
            ['visibility' => 'public']
        );*/

        try {
            $path = Storage::disk($this->disk)->putFileAs(
                $directory,
                $file,
                $filename,
                ['visibility' => 'public']
            );
        } catch (\Throwable $e) {
            Log::error('UPLOAD ERROR', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
        
        if (!$path) {
            throw new \Exception('Failed to upload file');
        }

        return $this->formatResponse($path, $file);
    }

    /**
     * Upload CV as PDF. DOC/DOCX files are converted locally before being stored.
     */
    public function uploadCvAsPdf(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            return $this->upload($file, $directory);
        }

        if (!in_array($extension, ['doc', 'docx'], true)) {
            return $this->upload($file, $directory);
        }

        $tempDir = storage_path('app/temp_cv_conversion');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $baseName = Str::uuid()->toString();
        $inputPath = "{$tempDir}/{$baseName}.{$extension}";
        $outputPath = "{$tempDir}/{$baseName}.pdf";

        try {
            copy($file->getRealPath(), $inputPath);
            $this->convertToPdf($inputPath, $tempDir);

            if (!file_exists($outputPath)) {
                throw new \Exception('File PDF hasil konversi tidak ditemukan');
            }

            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.pdf';
            $convertedFile = new UploadedFile(
                $outputPath,
                $originalName,
                'application/pdf',
                null,
                true
            );

            $result = $this->upload($convertedFile, $directory);
            $result['converted_from'] = $extension;
            $result['original_file_name'] = $file->getClientOriginalName();

            return $result;
        } finally {
            $this->cleanup([$inputPath, $outputPath]);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMany(array $files, string $directory): array
    {
        return collect($files)->map(function ($file) use ($directory) {
            return $this->upload($file, $directory);
        })->toArray();
    }

    /**
     * Delete file
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Generate temporary URL (for private files)
     */
    public function temporaryUrl(string $path, int $minutes = 10): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Get public URL (safe fallback)
     */
    public function url(string $path): string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);

        // Kalau driver support url()
        if (method_exists($disk, 'url')) {
            try {
                return $disk->url($path);
            } catch (\Throwable $e) {
                // fallback
            }
        }

        // fallback manual (IDCloudHost case)
        $baseUrl = config("filesystems.disks.{$this->disk}.url");

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        return Str::uuid() . '.' . $file->getClientOriginalExtension();
    }

    protected function convertToPdf(string $filePath, string $outputDir): void
    {
        $binary = $this->resolveLibreOfficeBinary();

        $process = new Process([
            $binary,
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
            Log::error('CV CONVERSION ERROR', [
                'binary' => $binary,
                'error' => $process->getErrorOutput(),
                'output' => $process->getOutput(),
            ]);

            throw new \Exception('Gagal mengonversi CV ke PDF');
        }
    }

    protected function resolveLibreOfficeBinary(): string
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

        $candidates = [
            '/usr/bin/libreoffice',
            '/usr/local/bin/libreoffice',
            '/opt/homebrew/bin/libreoffice',
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new \Exception(
            'LibreOffice tidak ditemukan di server. Install LibreOffice atau set LIBREOFFICE_BINARY ke path executable soffice/libreoffice.'
        );
    }

    protected function cleanup(array $files): void
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Standard response format
     */
    protected function formatResponse(string $path, UploadedFile $file): array
    {
        return [
            'path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_url' => $this->url($path),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }
}
