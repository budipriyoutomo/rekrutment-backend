<?php

namespace App\Domains\JobRequest\Requests;

use App\Core\Http\Requests\BaseRequest;

class JobRequestRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'department'      => ['nullable', 'string', 'max:255'],
            'location'        => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'in:full-time,part-time,contract,internship'],
            'headcount'       => ['nullable', 'integer', 'min:1'],
            'salary_range'    => ['nullable', 'string', 'max:255'],
            'needed_by'       => ['nullable', 'date'],
            'justification'   => ['nullable', 'string'],
            'requirements'    => ['nullable', 'array'],
            'requirements.*'  => ['string'],
            'requested_by'    => ['nullable', 'string', 'max:255'],
            'priority'        => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'status'          => ['nullable', 'string', 'in:pending,approved,rejected'],
            'reviewer_notes'  => ['nullable', 'string'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'department'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'location'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'employment_type' => ['sometimes', 'nullable', 'string', 'in:full-time,part-time,contract,internship'],
            'headcount'       => ['sometimes', 'nullable', 'integer', 'min:1'],
            'salary_range'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'needed_by'       => ['sometimes', 'nullable', 'date'],
            'justification'   => ['sometimes', 'nullable', 'string'],
            'requirements'    => ['sometimes', 'nullable', 'array'],
            'requirements.*'  => ['string'],
            'requested_by'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority'        => ['sometimes', 'nullable', 'string', 'in:low,normal,high,urgent'],
            'status'          => ['sometimes', 'nullable', 'string', 'in:pending,approved,rejected'],
            'reviewer_notes'  => ['sometimes', 'nullable', 'string'],
        ];
    }
}
