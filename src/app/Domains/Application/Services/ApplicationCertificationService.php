<?php

namespace App\Domains\Application\Services;

use App\Domains\Application\Models\ApplicationCertification;
use Illuminate\Support\Facades\DB;

class ApplicationCertificationService
{
    public function createMany(string $applicationId, array $items): void
    {  
        foreach ($items as $item) {
            ApplicationCertification::create(
                $this->map($applicationId, $item)
            );
        }
    }

    public function sync(string $applicationId, array $items): void
    {
        DB::transaction(function () use ($applicationId, $items) {

            $existingIds = ApplicationCertification::where('application_id', $applicationId)
                ->pluck('id')
                ->toArray();

            $incomingIds = [];

            foreach ($items as $item) {

                if (!empty($item['id'])) {
                    $incomingIds[] = $item['id'];

                    ApplicationCertification::where('id', $item['id'])
                        ->update($this->map($applicationId, $item));
                } else {
                    ApplicationCertification::create(
                        $this->map($applicationId, $item)
                    );
                }
            }

            $deleteIds = array_diff($existingIds, $incomingIds);

            if (!empty($deleteIds)) {
                ApplicationCertification::whereIn('id', $deleteIds)->delete();
            }
        });
    }

    private function map(string $applicationId, array $item): array
    {
        return [
            'application_id' => $applicationId,
            'course_name' => $item['courseName'] ?? null,
            'organization' => $item['organization'] ?? null,
            'year' => $item['year'] ?? null,
            'duration' => $item['duration'] ?? null,
        ];
    }
}