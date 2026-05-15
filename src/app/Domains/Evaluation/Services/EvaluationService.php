<?php

namespace App\Domains\Evaluation\Services;

use App\Core\Services\BaseService;
use App\Domains\Application\Models\Application;
use App\Domains\Evaluation\Models\Evaluation;
use Illuminate\Pagination\LengthAwarePaginator;

class EvaluationService extends BaseService
{
    public function __construct(Evaluation $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Evaluation::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('applicant_name', 'ilike', "%$search%")
                    ->orWhere('position', 'ilike', "%$search%")
                    ->orWhere('evaluator', 'ilike', "%$search%");
            });
        }

        if (!empty($filters['applicant_id'])) {
            $query->where('applicant_id', $filters['applicant_id']);
        }

        if (!empty($filters['recommendation'])) {
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
}
