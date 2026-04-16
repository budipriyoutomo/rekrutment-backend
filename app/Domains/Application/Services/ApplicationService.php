<?php

namespace App\Domains\Application\Services;


use App\Core\Services\BaseService;
use Illuminate\Support\Facades\DB;

use App\Domains\Application\Models\Application;

use App\Domains\Application\Services\ApplicationExperienceService;
use App\Domains\Application\Services\ApplicationEducationService;
use App\Domains\Application\Services\ApplicationCertificationService;

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
        return DB::transaction(function () use ($data, $relations) {

            $app = $this->create($data);

            // ✅ education
            $this->educationService->createMany(
                $app->id,
                $relations['education'] ?? []
            );

            // ✅ experience
            $this->experienceService->createMany(
                $app->id,
                $relations['workExperience'] ?? []
            );

            // ✅ certification
            $this->certificationService->createMany(
                $app->id,
                $relations['certifications'] ?? []
            );

            return $app;
        });
    }
}