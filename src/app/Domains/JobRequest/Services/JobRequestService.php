<?php

namespace App\Domains\JobRequest\Services;

use App\Core\Services\BaseService;
use App\Domains\JobRequest\Models\JobRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class JobRequestService extends BaseService
{
    public function __construct(JobRequest $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = JobRequest::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%$search%")
                    ->orWhere('department', 'ilike', "%$search%")
                    ->orWhere('requested_by', 'ilike', "%$search%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getDetail(string $id): JobRequest
    {
        return JobRequest::findOrFail($id);
    }

    public function createJobRequest(array $data): JobRequest
    {
        $data['status'] = $data['status'] ?? 'pending';
        $data['priority'] = $data['priority'] ?? 'normal';

        return $this->create($data);
    }

    public function updateJobRequest(string $id, array $data): JobRequest
    {
        return $this->update($id, $data);
    }

    public function approve(string $id, ?string $reviewerNotes = null): JobRequest
    {
        return $this->update($id, [
            'status'         => 'approved',
            'reviewer_notes' => $reviewerNotes,
        ]);
    }

    public function reject(string $id, ?string $reviewerNotes = null): JobRequest
    {
        return $this->update($id, [
            'status'         => 'rejected',
            'reviewer_notes' => $reviewerNotes,
        ]);
    }
}
