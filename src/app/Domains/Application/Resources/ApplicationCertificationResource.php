<?php

namespace App\Domains\Application\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationCertificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'courseName' => $this->course_name,
            'organization' => $this->organization,
            'year' => $this->year,
            'certificate' => $this->certificate,
        ];
    }
}