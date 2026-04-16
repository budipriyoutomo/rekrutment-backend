<?php

namespace App\Domains\Application\Actions;

use App\Domains\Application\DTO\ApplicationDTO;
use App\Domains\Application\Services\ApplicationService;

class SubmitApplicationAction
{
    public function __construct(
        private ApplicationService $service
    ) {}

    public function execute(ApplicationDTO $dto)
    {
        $app = $this->service->createFull(
            $dto->toArray(),
            [
                'education' => $dto->education,
                'workExperience' => $dto->workExperience,
                'certifications' => $dto->certifications,
            ]
        );

        return [
            'applicationId' => $app->id
        ];
    }
}