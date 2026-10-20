<?php

namespace App\Domains\Application\Services;

use App\Domains\Application\Models\ApplicationExperience;
use Illuminate\Support\Facades\DB;

class ApplicationExperienceService
{
    /**
     * Create multiple experiences (bulk insert)
     */
    public function createMany(string $applicationId, array $items): void
    {
        foreach ($items as $item) {
            ApplicationExperience::create(
                $this->map($applicationId, $item)
            );
        }
    }

    /**
     * Sync experiences (update + create + delete)
     */
    public function sync(string $applicationId, array $items): void
    {
        DB::transaction(function () use ($applicationId, $items) {

            $existingIds = ApplicationExperience::where('application_id', $applicationId)
                ->pluck('id')
                ->toArray();

            $incomingIds = [];

            foreach ($items as $item) {

                // UPDATE
                if (! empty($item['id'])) {
                    $incomingIds[] = $item['id'];

                    ApplicationExperience::where('id', $item['id'])
                        ->update($this->map($applicationId, $item));
                }

                // CREATE
                else {
                    ApplicationExperience::create(
                        $this->map($applicationId, $item)
                    );
                }
            }

            // DELETE yang tidak ada di payload
            $deleteIds = array_diff($existingIds, $incomingIds);

            if (! empty($deleteIds)) {
                ApplicationExperience::whereIn('id', $deleteIds)->delete();
            }
        });
    }

    /**
     * Mapping dari frontend ke database
     */
    private function map(string $applicationId, array $item): array
    {
        return [
            'application_id' => $applicationId,

            'company_name' => $item['companyName'] ?? null,
            'job_position' => $item['jobPosition'] ?? null,

            'year_start' => $item['yearStart'] ?? null,
            'year_end' => $item['yearEnd'] ?? null,

            'job_description' => $item['jobDescription'] ?? null,

            'restaurant_industry' => $item['restaurantIndustry'] ?? null,

            'position_category' => $item['positionCategory'] ?? null,

            'responsibilities' => $item['responsibilities'] ?? [],
            'pos_experience' => $this->choice($item['posExperience'] ?? null),

            'pos_system' => $this->choice($item['posSystem'] ?? null),

            'restaurant_type' => $this->choice($item['restaurantType'] ?? null),

            'shifts' => $item['shifts'] ?? [],

            'team_size' => $item['teamSize'] ?? null,
            'reason_for_leaving' => $item['reasonForLeaving'] ?? null,
        ];
    }

    /**
     * Form mengirim pilihan tunggal sebagai string ("yes", "Casual Dining"),
     * data lama berupa array. Dulu string dibuang (jadi null); sekarang
     * keduanya disimpan apa adanya, string kosong dianggap tidak diisi.
     */
    private function choice(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
