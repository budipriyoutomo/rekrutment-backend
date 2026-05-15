<?php

namespace App\Domains\Evaluation\Resources;

use App\Core\Http\Resources\BaseResource;

class EvaluationResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id' => $this->id,
            'applicantId' => $this->applicant_id,
            'applicantName' => $this->applicant_name,
            'position' => $this->position,
            'evaluator' => $this->evaluator,
            'date' => $this->date?->toDateString(),
            'communicationScore' => $this->communication_score,
            'technicalScore' => $this->technical_score,
            'experienceScore' => $this->experience_score,
            'cultureFitScore' => $this->culture_fit_score,
            'recommendation' => $this->recommendation,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'notes' => $this->notes,
            'overallScore' => $this->calculateOverallScore(),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ]);
    }

    private function calculateOverallScore(): float
    {
        $total = ($this->communication_score ?? 0)
            + ($this->technical_score ?? 0)
            + ($this->experience_score ?? 0)
            + ($this->culture_fit_score ?? 0);

        return $total > 0 ? round($total / 4, 1) : 0;
    }
}
