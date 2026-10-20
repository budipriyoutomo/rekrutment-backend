<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Models\Application;
use App\Domains\Application\Resources\ApplicationCertificationResource;
use App\Domains\Application\Resources\ApplicationEducationResource;
use App\Domains\Application\Resources\ApplicationExperienceResource;
use App\Domains\ProfileCompletion\Actions\CompleteProfileAction;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use App\Domains\ProfileCompletion\Requests\CompleteProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProfileCompletionController extends BaseApiController
{
    /**
     * Validasi token dan kembalikan data lamaran untuk mengisi form.
     *
     * Hanya data yang diisi pelamar sendiri yang dikirim — tanpa catatan HR,
     * status penolakan, maupun path/URL file (dokumen cukup nama & ukuran).
     */
    public function validateToken(string $token)
    {
        $record = ProfileCompletionToken::where('token', $token)->first();

        if ($error = $this->unusable($record)) {
            return $error;
        }

        $app = $record->application;

        return $this->success([
            'application_id' => $record->application_id,
            'email' => $record->email,
            'name' => $app?->personal_info['fullName'] ?? null,
            'position' => $app?->additional_info['positionApplied'] ?? null,
            'profile_completion_status' => $app?->profile_completion_status ?? 'undone',
            'expires_at' => $record->expires_at,
            'profile' => $app ? $this->profile($app) : null,
        ], 'Token valid');
    }

    /**
     * Simpan form lengkap ke lamaran pemilik token (multipart: `payload` JSON
     * + file cv/foto/ktp/ijazah opsional), lalu tandai selesai.
     */
    public function submit(string $token, CompleteProfileRequest $request, CompleteProfileAction $action)
    {
        $record = ProfileCompletionToken::where('token', $token)->first();

        if ($error = $this->unusable($record)) {
            return $error;
        }

        $app = $action->execute($record, ApplicationDTO::fromRequest($request), $request->documentFiles());

        return $this->success(['applicationId' => $app->id], 'Profil berhasil dilengkapi');
    }

    /**
     * @deprecated Hanya menandai status tanpa menyimpan data. Dipertahankan
     * sementara untuk frontend lama; frontend baru memakai `submit`.
     */
    public function complete(string $token)
    {
        $record = ProfileCompletionToken::where('token', $token)->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Link tidak valid atau sudah tidak tersedia.',
            ], 404);
        }

        if ($record->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Link sudah kedaluwarsa.',
            ], 410);
        }

        if ($record->isUsed()) {
            return $this->success(null, 'Profile completion sudah ditandai selesai sebelumnya.');
        }

        try {
            $record->application()->update([
                'profile_completion_status' => 'done',
            ]);

            $record->markAsUsed();

            return $this->success(null, 'Profile completion berhasil ditandai selesai.');
        } catch (\Throwable $e) {
            Log::error('Gagal menyelesaikan profile completion: '.$e->getMessage(), [
                'token' => $token,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan. Silakan coba lagi.',
            ], 500);
        }
    }

    /** Respons error bila token tidak bisa dipakai, null bila valid. */
    private function unusable(?ProfileCompletionToken $record): ?JsonResponse
    {
        [$status, $message] = match (true) {
            ! $record, ! $record->application => [404, 'Link tidak valid atau sudah tidak tersedia.'],
            $record->isExpired() => [410, 'Link sudah kedaluwarsa. Hubungi HR untuk mendapatkan link baru.'],
            $record->isUsed() => [409, 'Link ini sudah pernah digunakan.'],
            default => [null, null],
        };

        return $status ? response()->json(['success' => false, 'message' => $message], $status) : null;
    }

    private function profile(Application $app): array
    {
        $app->loadMissing(['educations', 'experiences', 'certifications']);

        $documents = [];
        foreach (['cv', 'foto', 'ktp', 'ijazah'] as $type) {
            if ($doc = $app->getDocument($type)) {
                $documents[$type] = ['name' => $doc['file_name'] ?? $type, 'size' => $doc['size'] ?? 0];
            }
        }

        return [
            'personalInfo' => $app->personal_info ?? [],
            'contactInfo' => $app->contact_info ?? [],
            'parentInfo' => $app->parent_info ?? [],
            'spouseInfo' => $app->spouse_info ?? [],
            'additionalInfo' => $app->additional_info ?? [],
            'education' => ApplicationEducationResource::collection($app->educations)->resolve(),
            'certifications' => ApplicationCertificationResource::collection($app->certifications)->resolve(),
            'workExperience' => ApplicationExperienceResource::collection($app->experiences)->resolve(),
            'documents' => $documents,
        ];
    }
}
