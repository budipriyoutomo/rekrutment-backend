<?php

namespace App\Domains\Assessment\Resources;

use App\Core\Http\Resources\BaseResource;

class AssessmentAssignmentResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id'            => $this->id,
            'applicationId' => $this->application_id,
            'assessmentId'  => $this->assessment_id,
            'status'        => $this->status,
            'score'         => $this->score !== null ? (float) $this->score : null,
            'passed'        => $this->passed !== null ? (bool) $this->passed : null,
            'answers'       => $this->answers ?? [],
            'expiresAt'     => $this->expires_at,
            'startedAt'     => $this->started_at,
            'submittedAt'   => $this->submitted_at,
            'createdAt'     => $this->created_at,
            'assessment'    => $this->whenLoaded('assessment', fn () => [
                'id'           => $this->assessment->id,
                'title'        => $this->assessment->title,
                'passingScore' => (int) $this->assessment->passing_score,
            ]),
            'applicant'     => $this->whenLoaded('application', fn () => [
                'id'       => $this->application->id,
                'name'     => $this->application->personal_info['fullName'] ?? null,
                'position' => $this->application->additional_info['positionApplied'] ?? null,
                'stage'    => $this->application->stage,
            ]),
        ]);
    }
}
