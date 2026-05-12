<?php

namespace App\Domains\Interview\Resources;

use App\Core\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class InterviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return $this->formatArray([
            'id' => $this->id,
            'applicantId' => $this->applicant_id,
            'applicant_id' => $this->applicant_id,
            'applicantName' => $this->applicant_name,
            'applicant_name' => $this->applicant_name,
            'position' => $this->position,
            'date' => optional($this->date)->format('Y-m-d') ?? $this->date,
            'time' => $this->time ? substr((string) $this->time, 0, 5) : null,
            'duration' => $this->duration,
            'type' => $this->type,
            'interviewers' => $this->interviewers ?? [],
            'status' => $this->status,
            'notes' => $this->notes,
            'room' => $this->room,
            'emailSent' => (bool) $this->email_sent,
            'email_sent' => (bool) $this->email_sent,
            'emailSentAt' => $this->email_sent_at,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'applicant' => $this->whenLoaded('applicant', function () {
                return [
                    'id' => $this->applicant->id,
                    'name' => $this->applicant->personal_info['fullName'] ?? $this->applicant_name,
                    'position' => $this->applicant->additional_info['positionApplied'] ?? $this->position,
                ];
            }),
        ]);
    }
}
