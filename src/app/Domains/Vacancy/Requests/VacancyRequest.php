<?php

namespace App\Domains\Vacancy\Requests;

use App\Core\Http\Requests\BaseRequest;

class VacancyRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'title'          => ['required', 'string', 'max:200'],
            'department'     => ['required', 'string', 'max:100'],
            'location'       => ['required', 'string', 'max:150'],
            'type'           => ['required', 'in:full-time,part-time,contract,internship'],
            'status'         => ['sometimes', 'in:open,closed,draft'],
            'salary'         => ['nullable', 'array'],
            'salary.min'     => ['nullable', 'numeric', 'min:0'],
            'salary.max'     => ['nullable', 'numeric', 'min:0', 'gte:salary.min'],
            'description'    => ['required', 'string', 'max:5000'],
            'requirements'   => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:255'],
            'posted_date'    => ['nullable', 'date'],
            'closing_date'   => ['nullable', 'date', 'after_or_equal:posted_date'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [
            'title'          => ['sometimes', 'string', 'max:200'],
            'department'     => ['sometimes', 'string', 'max:100'],
            'location'       => ['sometimes', 'string', 'max:150'],
            'type'           => ['sometimes', 'in:full-time,part-time,contract,internship'],
            'status'         => ['sometimes', 'in:open,closed,draft'],
            'salary'         => ['nullable', 'array'],
            'salary.min'     => ['nullable', 'numeric', 'min:0'],
            'salary.max'     => ['nullable', 'numeric', 'min:0', 'gte:salary.min'],
            'description'    => ['sometimes', 'string', 'max:5000'],
            'requirements'   => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:255'],
            'posted_date'    => ['nullable', 'date'],
            'closing_date'   => ['nullable', 'date', 'after_or_equal:posted_date'],
        ];
    }
}
