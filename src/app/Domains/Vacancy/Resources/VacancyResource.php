<?php

namespace App\Domains\Vacancy\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VacancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'department'     => $this->department,
            'location'       => $this->location,
            'type'           => $this->type,
            'status'         => $this->status,
            'salary'         => $this->salary,
            'description'    => $this->description,
            'requirements'   => $this->requirements ?? [],
            'postedDate'     => $this->posted_date?->format('Y-m-d'),
            'closingDate'    => $this->closing_date?->format('Y-m-d'),
            'applicantCount' => 0,
            'createdAt'      => $this->created_at,
            'updatedAt'      => $this->updated_at,
        ];
    }
}
