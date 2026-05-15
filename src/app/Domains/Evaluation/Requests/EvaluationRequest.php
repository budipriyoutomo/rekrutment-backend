<?php

namespace App\Domains\Evaluation\Requests;

use App\Core\Http\Requests\BaseRequest;

class EvaluationRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'applicant_id' => ['required', 'uuid', 'exists:applications,id'],
            'applicant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'evaluator' => ['required', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'communication_score' => ['required', 'integer', 'between:1,5'],
            'technical_score' => ['required', 'integer', 'between:1,5'],
            'experience_score' => ['required', 'integer', 'between:1,5'],
            'culture_fit_score' => ['required', 'integer', 'between:1,5'],
            'recommendation' => ['required', 'in:strong_hire,hire,hold,reject'],
            'strengths' => ['sometimes', 'nullable', 'string'],
            'improvements' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'applicant_id' => ['sometimes', 'uuid', 'exists:applications,id'],
            'applicant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'evaluator' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'communication_score' => ['sometimes', 'integer', 'between:1,5'],
            'technical_score' => ['sometimes', 'integer', 'between:1,5'],
            'experience_score' => ['sometimes', 'integer', 'between:1,5'],
            'culture_fit_score' => ['sometimes', 'integer', 'between:1,5'],
            'recommendation' => ['sometimes', 'in:strong_hire,hire,hold,reject'],
            'strengths' => ['sometimes', 'nullable', 'string'],
            'improvements' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $this->merge([
            'applicant_id' => $this->input('applicant_id', $this->input('applicantId')),
            'applicant_name' => $this->input('applicant_name', $this->input('applicantName')),
            'position' => $this->input('position'),
            'evaluator' => $this->input('evaluator'),
            'date' => $this->input('date'),
            'communication_score' => $this->input('communication_score', $this->input('communicationScore')),
            'technical_score' => $this->input('technical_score', $this->input('technicalScore')),
            'experience_score' => $this->input('experience_score', $this->input('experienceScore')),
            'culture_fit_score' => $this->input('culture_fit_score', $this->input('cultureFitScore')),
            'recommendation' => $this->input('recommendation'),
            'strengths' => $this->input('strengths'),
            'improvements' => $this->input('improvements'),
            'notes' => $this->input('notes'),
        ]);
    }
}
