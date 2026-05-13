<?php

namespace App\Domains\Interviewer\Services;

use App\Core\Services\BaseService;
use App\Domains\Interviewer\Models\Interviewer;
use Illuminate\Pagination\LengthAwarePaginator;

class InterviewerService extends BaseService
{
    public function __construct(Interviewer $interviewer)
    {
        parent::__construct($interviewer);
    }

    public function getList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Interviewer::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%")
                    ->orWhere('role', 'ilike', "%$search%")
                    ->orWhere('department', 'ilike', "%$search%")
                    ->orWhere('email', 'ilike', "%$search%");
            });
        }

        if (isset($filters['role']) && $filters['role'] !== null) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['department']) && $filters['department'] !== null) {
            $query->where('department', $filters['department']);
        }

        if (isset($filters['active']) && $filters['active'] !== null) {
            $query->where('active', filter_var($filters['active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return Interviewer
     */
    public function getDetail(string $id)
    {
        return Interviewer::findOrFail($id);
    }

    /**
     * @return Interviewer
     */
    public function createInterviewer(array $data)
    {
        return $this->create($data);
    }

    /**
     * @return Interviewer
     */
    public function updateInterviewer(string $id, array $data)
    {
        return $this->update($id, $data);
    }
}
