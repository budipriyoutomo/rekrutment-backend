<?php

namespace App\Domains\Preboarding\Services;

use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sisi kandidat: buka via token, unggah dokumen, kirim data.
 * Dokumen disimpan private — tidak ada URL publik; HR mengunduh lewat endpoint terautentikasi.
 */
class PreboardingSubmissionService
{
    /** 404 token tak dikenal, 410 link kedaluwarsa. */
    public function findByToken(string $token): Preboarding
    {
        $preboarding = Preboarding::with('application.vacancy', 'offer')->where('token', $token)->first();

        if (! $preboarding) {
            throw new HttpException(404, 'Link tidak valid atau sudah tidak tersedia.');
        }

        if ($preboarding->isTokenExpired()) {
            throw new HttpException(410, 'Link sudah kedaluwarsa. Hubungi HR untuk mendapatkan link baru.');
        }

        return $preboarding;
    }

    public function uploadDocument(string $token, string $type, UploadedFile $file): Preboarding
    {
        $preboarding = $this->editable($token);
        $current = $preboarding->document($type);

        if (($current['status'] ?? null) === Preboarding::DOC_VALID) {
            throw ValidationException::withMessages([
                'type' => 'Dokumen ini sudah diverifikasi HR dan tidak perlu diunggah ulang.',
            ]);
        }

        $disk = config('filesystems.upload_disk');
        $path = Storage::disk($disk)->putFileAs(
            "preboarding/{$preboarding->application_id}",
            $file,
            $type.'-'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            ['visibility' => 'private']
        );

        if (! $path) {
            throw new \RuntimeException('Gagal menyimpan dokumen.');
        }

        // File lama (mis. yang ditolak HR) dibuang supaya tidak menumpuk
        if (! empty($current['path'])) {
            Storage::disk($current['disk'] ?? $disk)->delete($current['path']);
        }

        $documents = $preboarding->documents ?? [];
        $documents[$type] = [
            'path' => $path,
            'disk' => $disk,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => Preboarding::DOC_PENDING,
            'note' => null,
            'uploaded_at' => now()->toISOString(),
            'reviewed_at' => null,
        ];

        $preboarding->update(['documents' => $documents]);

        return $preboarding;
    }

    public function submit(string $token, array $data): Preboarding
    {
        $preboarding = $this->editable($token);

        if ($missing = $preboarding->missingDocuments()) {
            throw ValidationException::withMessages([
                'documents' => 'Dokumen belum lengkap: '.collect($missing)
                    ->map(fn ($t) => Preboarding::REQUIRED_DOCUMENTS[$t])->implode(', ').'.',
            ]);
        }

        if ($rejected = $preboarding->rejectedDocuments()) {
            throw ValidationException::withMessages([
                'documents' => 'Unggah ulang dokumen yang diminta HR: '.collect($rejected)
                    ->map(fn ($t) => Preboarding::REQUIRED_DOCUMENTS[$t] ?? $t)->implode(', ').'.',
            ]);
        }

        $preboarding->update([
            'data' => $data,
            'status' => Preboarding::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return $preboarding;
    }

    private function editable(string $token): Preboarding
    {
        $preboarding = $this->findByToken($token);

        if (! $preboarding->isEditableByCandidate()) {
            throw new HttpException(409, $preboarding->status === Preboarding::STATUS_VERIFIED
                ? 'Data Anda sudah diverifikasi HR.'
                : 'Data Anda sudah terkirim dan sedang ditinjau HR.');
        }

        return $preboarding;
    }
}
