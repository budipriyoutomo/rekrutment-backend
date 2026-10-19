<?php

namespace App\Domains\Offer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representasi offer untuk HR. */
class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'applicationId' => $this->application_id,
            'candidateName' => $this->whenLoaded('application', fn () => $this->candidateName()),
            'candidateEmail' => $this->whenLoaded('application', fn () => $this->candidateEmail()),
            'vacancyId' => $this->vacancy_id,
            'position' => $this->position,
            'department' => $this->department,
            'location' => $this->location,
            'employmentType' => $this->employment_type,
            'baseSalary' => $this->base_salary,
            'allowances' => $this->allowances ?? [],
            'totalCompensation' => $this->base_salary + $this->totalAllowances(),
            'startDate' => $this->start_date?->format('Y-m-d'),
            'expiresAt' => $this->expires_at?->format('Y-m-d'),
            'terms' => $this->terms,
            'status' => $this->status,
            'publicUrl' => $this->publicUrl(),
            'sentAt' => $this->sent_at,
            'respondedAt' => $this->responded_at,
            'declineReason' => $this->decline_reason,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
