<?php

namespace App\Http\Controllers;

use App\Core\Http\Controllers\BaseApiController;
use App\Domains\Assessment\Actions\AutoGradeAssessmentAction;
use App\Domains\Assessment\Models\AssessmentAssignment;
use App\Domains\Assessment\Requests\SubmitAssessmentRequest;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint publik untuk kandidat mengerjakan tes lewat token dari email.
 * Tidak ada autentikasi: token yang menjadi kredensialnya.
 */
class AssessmentTakeController extends BaseApiController
{
    /**
     * Info tes sebelum dimulai. Sengaja BELUM mengirim soal — soal baru keluar
     * setelah start(), supaya kandidat tidak bisa membaca soal lebih dulu
     * tanpa timer berjalan.
     */
    public function validateToken(string $token)
    {
        $assignment = AssessmentAssignment::where('token', $token)
            ->with(['assessment', 'application'])
            ->first();

        if ($invalid = $this->guard($assignment)) {
            return $invalid;
        }

        return $this->success([
            'name'            => $assignment->application->personal_info['fullName'] ?? null,
            'position'        => $assignment->application->additional_info['positionApplied'] ?? null,
            'title'           => $assignment->assessment->title,
            'description'     => $assignment->assessment->description,
            'durationMinutes' => (int) $assignment->assessment->duration_minutes,
            'totalQuestions'  => count($assignment->effectiveQuestions()),
            'status'          => $assignment->status,
            'expiresAt'       => $assignment->expires_at,
            'startedAt'       => $assignment->started_at,
            'deadline'        => $assignment->deadline(),
        ], 'Token valid');
    }

    /**
     * Mulai mengerjakan: kunci waktu mulai lalu kirim soal.
     * Idempoten — memuat ulang halaman tidak me-reset timer.
     */
    public function start(string $token)
    {
        $assignment = AssessmentAssignment::where('token', $token)
            ->with(['assessment.questions'])
            ->first();

        if ($invalid = $this->guard($assignment)) {
            return $invalid;
        }

        if (!$assignment->started_at) {
            $assignment->update([
                'started_at' => now(),
                'status'     => 'in_progress',
            ]);

            $assignment->refresh()->load('assessment.questions');
        }

        return $this->success([
            'title'           => $assignment->assessment->title,
            'durationMinutes' => (int) $assignment->assessment->duration_minutes,
            'startedAt'       => $assignment->started_at,
            'deadline'        => $assignment->deadline(),
            'questions'       => $assignment->publicQuestions(),
        ], 'Tes dimulai');
    }

    /**
     * Kumpulkan jawaban lalu nilai otomatis.
     * Skor sengaja tidak dikembalikan ke kandidat — hasil disampaikan HR.
     */
    public function submit(string $token, SubmitAssessmentRequest $request, AutoGradeAssessmentAction $autoGrade)
    {
        $assignment = AssessmentAssignment::where('token', $token)
            ->with('assessment')
            ->first();

        if ($invalid = $this->guard($assignment)) {
            return $invalid;
        }

        if (!$assignment->started_at) {
            return $this->error('Tes belum dimulai.', 422);
        }

        if ($assignment->isPastDeadline()) {
            return $this->error('Waktu pengerjaan sudah habis. Hubungi HR untuk mendapatkan kesempatan baru.', 422);
        }

        $autoGrade->execute($assignment, $request->validated()['answers'] ?? []);

        return $this->success(null, 'Jawaban berhasil dikumpulkan. Hasil akan diinformasikan oleh tim HR.');
    }

    /**
     * Alasan token tidak bisa dipakai, atau null bila valid.
     */
    private function guard(?AssessmentAssignment $assignment): ?JsonResponse
    {
        if (!$assignment) {
            return $this->error('Link tidak valid atau sudah tidak tersedia.', 404);
        }

        if ($assignment->isSubmitted()) {
            return $this->error('Tes ini sudah pernah Anda kerjakan.', 409);
        }

        if ($assignment->isExpired()) {
            return $this->error('Link sudah kedaluwarsa. Hubungi HR untuk mendapatkan link baru.', 410);
        }

        return null;
    }
}
