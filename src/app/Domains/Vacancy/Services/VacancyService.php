<?php

namespace App\Domains\Vacancy\Services;

use App\Core\Services\BaseService;
use App\Domains\JobRequest\Models\JobRequest;
use App\Domains\Vacancy\Models\Vacancy;
use App\Enums\PipelineStage;
use Illuminate\Validation\ValidationException;

class VacancyService extends BaseService
{
    public function __construct(Vacancy $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters = [], int $perPage = 20)
    {
        $query = $this->withApplicantCounts(Vacancy::query());

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (! empty($filters['search'])) {
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
        return $this->withApplicantCounts(Vacancy::query())->findOrFail($id);
    }

    /** Tambah applications_count & hired_count untuk VacancyResource. */
    private function withApplicantCounts($query)
    {
        return $query->withCount([
            'applications',
            'applications as hired_count' => fn ($q) => $q->where('stage', PipelineStage::HIRED->value),
        ]);
    }

    public function createVacancy(array $data): Vacancy
    {
        if (! isset($data['status'])) {
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

        return $this->getDetail($vacancy->id);
    }

    public function closeVacancy(string $id): Vacancy
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->update(['status' => 'closed']);

        return $this->getDetail($vacancy->id);
    }

    public function deleteVacancy(string $id): void
    {
        Vacancy::findOrFail($id)->delete();
    }

    /**
     * Tentukan lowongan untuk lamaran baru. `vacancyId` eksplisit (dari tombol
     * Lamar di halaman lowongan) dipakai bila title-nya cocok dengan posisi yang
     * dilamar; kalau pelamar mengganti posisi, atau tidak ada id, cocokkan otomatis
     * ke lowongan open dengan title = posisi (utamakan lokasi sama, lalu terbaru).
     */
    public function resolveForApplication(?string $vacancyId, ?string $position, ?string $location): ?string
    {
        if ($vacancyId) {
            $explicit = Vacancy::find($vacancyId);

            if ($explicit && $explicit->status === 'open' && ($position === null || $explicit->title === $position)) {
                return $explicit->id;
            }
        }

        if (! $position) {
            return null;
        }

        return Vacancy::query()
            ->where('status', 'open')
            ->where('title', $position)
            ->when($location, fn ($q) => $q->orderByRaw('CASE WHEN location = ? THEN 0 ELSE 1 END', [$location]))
            ->latest()
            ->value('id');
    }

    /**
     * Tutup lowongan open yang kuota hired-nya sudah terpenuhi. Dipanggil setelah
     * pelamar dipindah ke stage hired. Mengembalikan id lowongan yang ditutup.
     */
    public function closeFilledVacancies(array $vacancyIds): array
    {
        $vacancyIds = array_values(array_unique(array_filter($vacancyIds)));

        if (empty($vacancyIds)) {
            return [];
        }

        $closed = [];

        Vacancy::query()
            ->whereIn('id', $vacancyIds)
            ->where('status', 'open')
            ->whereNotNull('headcount')
            ->get()
            ->each(function (Vacancy $vacancy) use (&$closed) {
                if ($vacancy->isHeadcountFilled()) {
                    $vacancy->update(['status' => 'closed']);
                    $closed[] = $vacancy->id;
                }
            });

        return $closed;
    }

    /**
     * Buat lowongan draft dari job request yang sudah disetujui. Satu job request
     * hanya boleh punya satu lowongan (dijaga juga oleh unique index).
     */
    public function createFromJobRequest(string $jobRequestId): Vacancy
    {
        $jobRequest = JobRequest::findOrFail($jobRequestId);

        if ($jobRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'job_request' => 'Hanya job request yang sudah disetujui yang bisa dibuatkan lowongan.',
            ]);
        }

        if ($jobRequest->vacancy()->exists()) {
            throw ValidationException::withMessages([
                'job_request' => 'Job request ini sudah memiliki lowongan.',
            ]);
        }

        $vacancy = $this->create([
            'job_request_id' => $jobRequest->id,
            'title' => $jobRequest->title,
            'department' => $jobRequest->department ?? '',
            'location' => $jobRequest->location ?? '',
            'type' => $jobRequest->employment_type ?: 'full-time',
            'status' => 'draft',
            'headcount' => $jobRequest->headcount,
            'description' => $jobRequest->justification ?? '',
            'requirements' => $jobRequest->requirements ?? [],
            'closing_date' => $jobRequest->needed_by,
        ]);

        return $this->getDetail($vacancy->id);
    }
}
