<?php

namespace App\Domains\Assessment\Services;

use App\Core\Services\BaseService;
use App\Domains\Application\Models\Application;
use App\Domains\Assessment\Actions\SendAssessmentInvitationAction;
use App\Domains\Assessment\Models\Assessment;
use App\Domains\Assessment\Models\AssessmentAssignment;
use App\Enums\PipelineStage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AssessmentService extends BaseService
{
    public function __construct(
        Assessment $model,
        private readonly SendAssessmentInvitationAction $sendInvitationAction,
    ) {
        parent::__construct($model);
    }

    /*
    |--------------------------------------------------------------------------
    | PAKET TES
    |--------------------------------------------------------------------------
    */

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Assessment::query()->withCount('questions');

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $query->where('title', 'ilike', '%' . $filters['search'] . '%');
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getDetail(string $id): Assessment
    {
        return Assessment::with('questions')->findOrFail($id);
    }

    public function createAssessment(array $data): Assessment
    {
        return DB::transaction(function () use ($data) {
            $assessment = Assessment::create([
                'title'            => $data['title'],
                'description'      => $data['description'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'passing_score'    => $data['passing_score'] ?? 70,
                'is_active'        => $data['is_active'] ?? true,
            ]);

            $this->syncQuestions($assessment, $data['questions'] ?? []);

            return $assessment->load('questions');
        });
    }

    public function updateAssessment(string $id, array $data): Assessment
    {
        return DB::transaction(function () use ($id, $data) {
            $assessment = Assessment::findOrFail($id);

            $assessment->update(array_filter([
                'title'            => $data['title'] ?? null,
                'description'      => $data['description'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'passing_score'    => $data['passing_score'] ?? null,
                'is_active'        => $data['is_active'] ?? null,
            ], fn ($value) => $value !== null));

            // Soal hanya disentuh bila klien memang mengirim field-nya, agar
            // update parsial (mis. ganti judul saja) tidak menghapus bank soal.
            if (array_key_exists('questions', $data)) {
                $this->syncQuestions($assessment, $data['questions'] ?? []);
            }

            return $assessment->refresh()->load('questions');
        });
    }

    public function deleteAssessment(string $id): Assessment
    {
        $assessment = Assessment::findOrFail($id);

        // FK assessment_assignments.assessment_id sengaja restrict: hasil tes
        // kandidat tidak boleh ikut hilang. Beri pesan yang jelas, bukan error SQL.
        if ($assessment->assignments()->exists()) {
            throw new \RuntimeException(
                'Paket tes ini sudah pernah dikirim ke kandidat sehingga tidak bisa dihapus. Nonaktifkan saja lewat status.'
            );
        }

        $assessment->delete();

        return $assessment;
    }

    /**
     * Menyelaraskan bank soal: soal ber-id diperbarui, soal tanpa id dibuat,
     * soal yang tidak lagi dikirim dihapus.
     */
    private function syncQuestions(Assessment $assessment, array $questions): void
    {
        $keptIds = [];

        foreach (array_values($questions) as $index => $question) {
            $payload = [
                'question'       => $question['question'],
                'options'        => $question['options'],
                'correct_answer' => $question['correct_answer'],
                'score'          => $question['score'] ?? 1,
                'order'          => $question['order'] ?? $index,
            ];

            $record = !empty($question['id'])
                ? $assessment->questions()->whereKey($question['id'])->first()
                : null;

            if ($record) {
                $record->update($payload);
            } else {
                $record = $assessment->questions()->create($payload);
            }

            $keptIds[] = $record->id;
        }

        $assessment->questions()->whereKeyNot($keptIds)->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | PENUGASAN KE KANDIDAT
    |--------------------------------------------------------------------------
    */

    public function assignToApplicant(string $assessmentId, string $applicationId): AssessmentAssignment
    {
        $assessment  = Assessment::with('questions')->findOrFail($assessmentId);
        $application = Application::findOrFail($applicationId);

        if (!$assessment->is_active) {
            throw new \RuntimeException('Paket tes tidak aktif dan tidak bisa dikirim ke kandidat.');
        }

        if ($assessment->questions->isEmpty()) {
            throw new \RuntimeException('Paket tes belum memiliki soal.');
        }

        // Email dikirim di dalam transaksi: bila pengiriman gagal, baris
        // assignment ikut di-rollback sehingga tidak ada token yatim.
        return DB::transaction(function () use ($assessment, $application) {
            $assignment = $this->sendInvitationAction->execute($application, $assessment);

            $application->update(['stage' => PipelineStage::ASSESSMENT->value]);

            return $assignment->load('assessment', 'application');
        });
    }

    public function getAssignments(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AssessmentAssignment::query()->with(['assessment', 'application']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['assessment_id'])) {
            $query->where('assessment_id', $filters['assessment_id']);
        }

        if (!empty($filters['application_id'])) {
            $query->where('application_id', $filters['application_id']);
        }

        if (array_key_exists('passed', $filters) && $filters['passed'] !== null) {
            $query->where('passed', (bool) $filters['passed']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function getAssignmentDetail(string $id): AssessmentAssignment
    {
        return AssessmentAssignment::with(['assessment.questions', 'application'])->findOrFail($id);
    }
}
