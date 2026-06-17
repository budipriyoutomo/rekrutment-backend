<?php

namespace App\Domains\Vacancy\Services;

use App\Core\Services\BaseService;
use App\Domains\Vacancy\Models\Vacancy;

class VacancyService extends BaseService
{
    public function __construct(Vacancy $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 20)
    {
        $query = Vacancy::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('department', 'ilike', "%{$search}%")
                  ->orWhere('location', 'ilike', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getDetail(string $id): Vacancy
    {
        return Vacancy::findOrFail($id);
    }

    public function createVacancy(array $data): Vacancy
    {
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }

        if ($data['status'] === 'open' && empty($data['posted_date'])) {
            $data['posted_date'] = now()->toDateString();
        }

        return $this->create($data);
    }

    public function updateVacancy(string $id, array $data): Vacancy
    {
        $vacancy = Vacancy::findOrFail($id);

        if (
            isset($data['status']) &&
            $data['status'] === 'open' &&
            $vacancy->status !== 'open' &&
            empty($data['posted_date']) &&
            empty($vacancy->posted_date)
        ) {
            $data['posted_date'] = now()->toDateString();
        }

        $vacancy->update($data);

        return $vacancy->refresh();
    }

    public function closeVacancy(string $id): Vacancy
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->update(['status' => 'closed']);

        return $vacancy->refresh();
    }

    public function deleteVacancy(string $id): void
    {
        Vacancy::findOrFail($id)->delete();
    }
}
