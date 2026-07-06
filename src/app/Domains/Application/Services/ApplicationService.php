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

        // 🔍 filter berdasarkan inputan quick apply (/apply)
        if (!empty($filters['gender'])) {
            $query->where('personal_info->gender', $filters['gender']);
        }

        if (!empty($filters['position'])) {
            $query->where('additional_info->positionApplied', $filters['position']);
        }

        if (!empty($filters['workLocation'])) {
            $query->where('additional_info->workLocation', $filters['workLocation']);
        }

        if (!empty($filters['jobSource'])) {
            $query->where('additional_info->jobSource', $filters['jobSource']);
        }

        if (!empty($filters['hasVehicle'])) {
            $query->where('additional_info->hasVehicle', $filters['hasVehicle']);
        }

        if (!empty($filters['workedBefore'])) {
            $query->where('additional_info->workedAtCompany', $filters['workedBefore']);
        }

        if (!empty($filters['domicile'])) {
            $domicile = $filters['domicile'];

            $query->where(function ($q) use ($domicile) {
                $q->where('contact_info->currentAddress', 'ilike', "%$domicile%")
                  ->orWhere('contact_info->homeAddress', 'ilike', "%$domicile%");
            });
        }

        // usia disimpan sebagai string di JSON, ambil digitnya saja sebelum cast
        if (!empty($filters['ageMin'])) {
            $query->whereRaw(
                "NULLIF(regexp_replace(additional_info->>'age', '\D', '', 'g'), '')::int >= ?",
                [(int) $filters['ageMin']]
            );
        }

        if (!empty($filters['ageMax'])) {
            $query->whereRaw(
                "NULLIF(regexp_replace(additional_info->>'age', '\D', '', 'g'), '')::int <= ?",
                [(int) $filters['ageMax']]
            );
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
