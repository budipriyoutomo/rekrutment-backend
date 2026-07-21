<?php

namespace App\Domains\Assessment\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * Soal versi HR — memuat kunci jawaban. Jangan dipakai di endpoint publik.
 */
class AssessmentQuestionResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id'            => $this->id,
            'assessmentId'  => $this->assessment_id,
            'question'      => $this->question,
            'options'       => $this->options ?? [],
            'correctAnswer' => $this->correct_answer,
            'score'         => (int) $this->score,
            'order'         => (int) $this->order,
        ]);
    }
}
