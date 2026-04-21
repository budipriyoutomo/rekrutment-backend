<?php

namespace App\Domains\Application\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationExperienceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'jobPosition' => $this->job_position,
            'yearStart' => $this->year_start,
            'yearEnd' => $this->year_end,
            'jobDescription' => $this->job_description,

            'restaurantIndustry' => $this->restaurant_industry,
            'restaurantType' => $this->restaurant_type,
            'positionCategory' => $this->position_category,

            'responsibilities' => $this->responsibilities,
            'posExperience' => $this->pos_experience,
            'posSystem' => $this->pos_system,
            'shifts' => $this->shifts,

            'teamSize' => $this->team_size,
            'reasonForLeaving' => $this->reason_for_leaving,
        ];
    }
}