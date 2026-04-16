<?php

namespace App\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function upload(UploadedFile $file, string $type): array
    {
        $path = $file->store("applications/{$type}", 'public');

        return [
            'fileUrl' => Storage::url($path),
            'fileName' => $file->getClientOriginalName(),
        ];
    }
}