<?php

namespace App\Domains\Vacancy\Requests;

use App\Core\Http\Requests\BaseRequest;

class VacancyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:200'],
            'department'   => ['required', 'string', 'max:100'],
            'location'     => ['required', 'string', 'max:150'],
            'type'         => ['required', 'in:full-time,part-time,contract,internship'],
            'status'       => ['sometimes', 'in:open,closed,draft'],
            'salary'       => ['nullable', 'string', 'max:100'],
            'description'  => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:255'],
            'posted_date'  => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:posted_date'],
        ];
    }
}
