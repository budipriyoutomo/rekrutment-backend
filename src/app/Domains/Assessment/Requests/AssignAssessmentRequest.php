<?php

namespace App\Domains\Assessment\Requests;

use App\Core\Http\Requests\BaseRequest;

class AssignAssessmentRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'application_id' => ['required', 'uuid', 'exists:applications,id'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $this->merge([
            'application_id' => $this->input('application_id', $this->input('applicationId')),
        ]);
    }
}
