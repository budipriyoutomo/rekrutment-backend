<?php

namespace App\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


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