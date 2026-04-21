<?php

namespace App\Domains\Application\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationEducationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'schoolName' => $this->school_name,
            'city' => $this->city,
            'yearStart' => $this->year_start,
            'yearEnd' => $this->year_end,
            'major' => $this->major,
            'gpa' => $this->gpa,
            'certificate' => $this->certificate,
        ];
    }
}