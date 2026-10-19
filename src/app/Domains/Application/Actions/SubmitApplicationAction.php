<?php

namespace App\Domains\Application\Actions;

use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Services\ApplicationService;
use App\Domains\Vacancy\Services\VacancyService;

class SubmitApplicationAction
{
    public function __construct(
        private ApplicationService $service,
        private VacancyService $vacancyService,
    ) {}

    public function execute(ApplicationDTO $dto)
    {
        $data = $dto->toArray();
        $data['vacancy_id'] = $this->vacancyService->resolveForApplication(
            $dto->vacancyId,
            $dto->additionalInfo['positionApplied'] ?? null,
            $dto->additionalInfo['workLocation'] ?? null,
        );

        $app = $this->service->createFull(
            $data,
            [
                'education' => $dto->education,
                'workExperience' => $dto->workExperience,
                'certifications' => $dto->certifications,
            ]
        );

        return [
            'applicationId' => $app->id,
        ];
    }
}
