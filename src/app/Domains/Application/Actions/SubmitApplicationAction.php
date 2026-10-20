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

    /** @param  array<string, array>  $documents  dokumen yang sudah diunggah, per tipe */
    public function execute(ApplicationDTO $dto, array $documents = [])
    {
        $data = $dto->toArray();
        if ($documents !== []) {
            $data['documents'] = $documents;
        }
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
