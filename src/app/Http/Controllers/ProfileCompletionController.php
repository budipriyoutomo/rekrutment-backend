<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\ProfileCompletion\Models\ProfileCompletionToken;
use Illuminate\Support\Facades\Log;

class ProfileCompletionController extends BaseApiController
{
    /**
     * Validasi token dan kembalikan info dasar pelamar untuk disambut di halaman form.
     */
    public function validateToken(string $token)
    {
        $record = ProfileCompletionToken::where('token', $token)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Link tidak valid atau sudah tidak tersedia.',
            ], 404);
        }

        if ($record->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Link sudah kedaluwarsa. Hubungi HR untuk mendapatkan link baru.',
            ], 410);
        }

        if ($record->isUsed()) {
            return response()->json([
                'success' => false,
                'message' => 'Link ini sudah pernah digunakan.',
            ], 409);
        }

        $app = $record->application;

        return $this->success([
            'application_id'             => $record->application_id,
            'email'                      => $record->email,
            'name'                       => $app?->personal_info['fullName'] ?? null,
            'position'                   => $app?->additional_info['positionApplied'] ?? null,
            'profile_completion_status'  => $app?->profile_completion_status ?? 'undone',
            'expires_at'                 => $record->expires_at,
        ], 'Token valid');
    }

    /**
     * Tandai profile completion sebagai selesai (done).
     * Dipanggil setelah candidate berhasil submit form.
     */
    public function complete(string $token)
    {
        $record = ProfileCompletionToken::where('token', $token)->first();

        if (!$record) {
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
            Log::error('Gagal menyelesaikan profile completion: ' . $e->getMessage(), [
                'token' => $token,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan. Silakan coba lagi.',
            ], 500);
        }
    }
}
