<?php

namespace App\Domains\Application\Services;

use App\Domains\Application\Models\ApplicationEducation;
use Illuminate\Support\Facades\DB;

class ApplicationEducationService
{
    public function createMany(string $applicationId, array $items): void
    {
        foreach ($items as $item) {
            ApplicationEducation::create(
                $this->map($applicationId, $item)
            );
        }
    }

    public function sync(string $applicationId, array $items): void
    {
        DB::transaction(function () use ($applicationId, $items) {

            $existingIds = ApplicationEducation::where('application_id', $applicationId)
                ->pluck('id')
                ->toArray();

            $incomingIds = [];

            foreach ($items as $item) {

                if (!empty($item['id'])) {
                    $incomingIds[] = $item['id'];

                    ApplicationEducation::where('id', $item['id'])
                        ->update($this->map($applicationId, $item));
                } else {
                    ApplicationEducation::create(
                        $this->map($applicationId, $item)
                    );
                }
            }

            $deleteIds = array_diff($existingIds, $incomingIds);

            if (!empty($deleteIds)) {
                ApplicationEducation::whereIn('id', $deleteIds)->delete();
            }
        });
    }

    private function map(string $applicationId, array $item): array
    {
        return [
            'application_id' => $applicationId,
            'level' => $item['level'] ?? null,
            'school_name' => $item['schoolName'] ?? null,
            'city' => $item['city'] ?? null,
            'year_start' => $item['yearStart'] ?? null,
            'year_end' => $item['yearEnd'] ?? null,
            'major' => $item['major'] ?? null,
            'gpa' => $item['gpa'] ?? null,
            'certificate' => $item['certificate'] ?? null,
        ];
    }
}