<?php

namespace App\Domains\Assessment\Resources;

use App\Core\Http\Resources\BaseResource;

class AssessmentResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id'              => $this->id,
            'title'           => $this->title,
            'description'     => $this->description,
            'durationMinutes' => (int) $this->duration_minutes,
            'passingScore'    => (int) $this->passing_score,
            'isActive'        => (bool) $this->is_active,
            'questionsCount'  => $this->questions_count ?? $this->whenLoaded('questions', fn () => $this->questions->count()),
            'questions'       => AssessmentQuestionResource::collection($this->whenLoaded('questions')),
            'createdAt'       => $this->created_at,
            'updatedAt'       => $this->updated_at,
        ]);
    }
}
