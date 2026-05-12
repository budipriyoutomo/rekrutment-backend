<?php

namespace App\Domains\Interview\Requests;

use App\Core\Http\Requests\BaseRequest;

class InterviewRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'applicant_id' => ['required', 'uuid', 'exists:applications,id'],
            'applicant_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:online,offline,technical_test'],
            'interviewers' => ['required', 'array', 'min:1'],
            'interviewers.*' => ['required', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'send_email' => ['boolean'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'applicant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'in:online,offline,technical_test'],
            'interviewers' => ['sometimes', 'array', 'min:1'],
            'interviewers.*' => ['required_with:interviewers', 'string', 'max:255'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled,no_show'],
            'room' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $this->merge([
            'applicant_id' => $this->input('applicant_id', $this->input('applicantId')),
            'applicant_name' => $this->input('applicant_name', $this->input('applicantName')),
            'send_email' => $this->boolean('send_email', $this->boolean('sendEmail')),
        ]);
    }
}
