<?php

namespace App\Domains\Application\Services;

use App\Core\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Domains\Application\Models\Application;

class ApplicationService extends BaseService
{
    public function __construct(
        Application $model,
        private ApplicationExperienceService $experienceService,
        private ApplicationEducationService $educationService,
        private ApplicationCertificationService $certificationService,
    ) {
        parent::__construct($model);
    }

    /**
     * ============================================
     * CREATE FULL APPLICATION
     * ============================================
     */
    public function createFull(array $data, array $relations): Application
    {
        $relations = array_merge([
            'education' => [],
            'workExperience' => [],
            'certifications' => [],
        ], $relations);

        // 🔥 prevent duplicate (simple)
        if (!empty($data['personal_info']['email'])) {
            $exists = Application::where('personal_info->email', $data['personal_info']['email'])
                ->latest()
                ->first();

            if ($exists) {
                return $exists;
            }
        }

        try {
            return DB::transaction(function () use ($data, $relations) {

                $app = $this->create($data);

                $this->educationService->createMany($app->id, $relations['education']);
                $this->experienceService->createMany($app->id, $relations['workExperience']);
                $this->certificationService->createMany($app->id, $relations['certifications']);

                return $app;
            });

        } catch (\Throwable $e) {
            report($e);
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * ============================================
     * GET LIST (WITH FILTER + PAGINATION)
     * ============================================
     */
    public function getList(array $filters = [], int $perPage = 10)
    {
        $query = Application::query();

        // 🔍 filter stage/status
        $stage = $filters['stage'] ?? $filters['status'] ?? null;
        if (!empty($stage)) {
            $query->where('stage', $stage);
        }

        // 🔍 search name / email
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('personal_info->fullName', 'ilike', "%$search%")
                  ->orWhere('contact_info->email', 'ilike', "%$search%");
            });
        }

        // 🔍 filter date range
        if (!empty($filters['startDate']) && !empty($filters['endDate'])) {
            $query->whereBetween('created_at', [
                $filters['startDate'],
                $filters['endDate']
            ]);
        }

        return $query
            ->latest()
            ->paginate($perPage);
    }

    /**
     * ============================================
     * GET DETAIL
     * ============================================
     */
    public function getDetail(string $id): Application
    {
        return Application::with([
            'educations',
            'experiences',
            'certifications'
        ])->findOrFail($id);
    }

    /**
     * ============================================
     * UPDATE STAGE
     * ============================================
     */
    public function updateStatus(string $id, string $stage): Application
    {
        $app = Application::findOrFail($id);

        $app->update([
            'stage' => $stage
        ]);

        return $app;
    }

    /**
     * ============================================
     * ADD HR NOTE
     * ============================================
     */
    public function addNote(string $id, string $text): Application
    {
        $app = Application::findOrFail($id);
        $notes = $app->notes ?? [];

        array_unshift($notes, [
            'id' => Str::uuid()->toString(),
            'text' => $text,
            'date' => now()->toDateString(),
            'createdAt' => now()->toISOString(),
        ]);

        $app->update([
            'notes' => $notes,
        ]);

        return $app->refresh();
    }
}
