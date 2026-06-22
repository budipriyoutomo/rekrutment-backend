<?php

namespace App\Domains\JobRequest\Resources;

use App\Core\Http\Resources\BaseResource;

class JobRequestResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id'             => $this->id,
            'title'          => $this->title,
            'department'     => $this->department,
            'location'       => $this->location,
            'employmentType' => $this->employment_type,
            'headcount'      => (int) $this->headcount,
            'salaryRange'    => $this->salary_range,
            'neededBy'       => $this->needed_by,
            'justification'  => $this->justification,
            'requirements'   => $this->requirements ?? [],
            'requesterName'  => $this->requested_by,
            'priority'       => $this->priority,
            'status'         => $this->status,
            'reviewerNotes'  => $this->reviewer_notes,
            'createdAt'      => $this->created_at,
            'updatedAt'      => $this->updated_at,
        ]);
    }
}
