<?php

namespace App\Domains\Application\Services;

use App\Core\Services\BaseService;
use Illuminate\Support\Facades\DB;

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

    public function createFull(array $data, array $relations): Application
    {
        // 🔥 normalize
        $relations = array_merge([
            'education' => [],
            'workExperience' => [],
            'certifications' => [],
        ], $relations);

        // 🔥 anti duplicate (simple)
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

                // education
                $this->educationService->createMany(
                    $app->id,
                    $relations['education']
                );

                // experience
                $this->experienceService->createMany(
                    $app->id,
                    $relations['workExperience']
                );

                // certification
                $this->certificationService->createMany(
                    $app->id,
                    $relations['certifications']
                );

                return $app;
            });

        } catch (\Throwable $e) {
            report($e);
            throw new \Exception($e->getMessage());
        }
    }
}