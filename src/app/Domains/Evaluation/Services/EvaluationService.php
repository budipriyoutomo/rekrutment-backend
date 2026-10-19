<?php

namespace App\Domains\Evaluation\Services;

use App\Core\Services\BaseService;
use App\Domains\Application\Models\Application;
use App\Domains\Evaluation\Models\Evaluation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EvaluationService extends BaseService
{
    public const RECOMMENDATIONS = ['strong_hire', 'hire', 'hold', 'reject'];

    public function __construct(Evaluation $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Evaluation::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'ilike', "%$search%")
                    ->orWhere('position', 'ilike', "%$search%")
                    ->orWhere('evaluator', 'ilike', "%$search%");
            });
        }

        if (! empty($filters['applicant_id'])) {
            $query->where('applicant_id', $filters['applicant_id']);
        }

        if (! empty($filters['recommendation'])) {
            $query->where('recommendation', $filters['recommendation']);
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getDetail(string $id): Evaluation
    {
        return Evaluation::findOrFail($id);
    }

    public function createEvaluation(array $data): Evaluation
    {
        $applicant = Application::find($data['applicant_id']);

        $data['applicant_name'] = $data['applicant_name'] ?? $applicant?->personal_info['fullName'] ?? 'Tanpa Nama';
        $data['position'] = $data['position'] ?? $applicant?->additional_info['positionApplied'] ?? '-';
        $data['date'] = $data['date'] ?? now()->toDateString();

        return $this->create($data);
    }

    public function updateEvaluation(string $id, array $data): Evaluation
    {
        return $this->update($id, $data);
    }

    /**
     * Ringkasan seluruh evaluasi interview seorang kandidat, bahan keputusan HR
     * lanjut ke offer atau tolak. Skor rata-rata dalam skala 1-5 (null bila belum
     * ada evaluasi).
     *
     * @return array{count:int, averageScores:array<string,float|null>, recommendations:array<string,int>, evaluations:Collection}
     */
    public function summaryForApplicant(string $applicantId): array
    {
        Application::findOrFail($applicantId);

        $evaluations = Evaluation::query()
            ->where('applicant_id', $applicantId)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        $avg = fn (string $column) => $evaluations->isEmpty()
            ? null
            : round((float) $evaluations->avg($column), 1);

        $averageScores = [
            'communication' => $avg('communication_score'),
            'technical' => $avg('technical_score'),
            'experience' => $avg('experience_score'),
            'cultureFit' => $avg('culture_fit_score'),
        ];
        $averageScores['overall'] = $evaluations->isEmpty()
            ? null
            : round((float) $evaluations->avg(fn (Evaluation $e) => (
                $e->communication_score + $e->technical_score + $e->experience_score + $e->culture_fit_score
            ) / 4), 1);

        $recommendations = array_fill_keys(self::RECOMMENDATIONS, 0);
        foreach ($evaluations as $evaluation) {
            $recommendations[$evaluation->recommendation]++;
        }

        return [
            'count' => $evaluations->count(),
            'averageScores' => $averageScores,
            'recommendations' => $recommendations,
            'evaluations' => $evaluations,
        ];
    }
}
