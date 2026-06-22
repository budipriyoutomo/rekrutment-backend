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

        $camelMap = [
            'applicantId'        => 'applicant_id',
            'applicantName'      => 'applicant_name',
            'communicationScore' => 'communication_score',
            'technicalScore'     => 'technical_score',
            'experienceScore'    => 'experience_score',
            'cultureFitScore'    => 'culture_fit_score',
        ];

        $extras = [];
        foreach ($camelMap as $camel => $snake) {
            if ($this->has($camel)) {
                $extras[$snake] = $this->input($camel);
            }
        }

        if ($extras) {
            $this->merge($extras);
        }
    }
}
