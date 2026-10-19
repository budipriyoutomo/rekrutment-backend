<?php

namespace App\Domains\Offer\Requests;

use App\Core\Http\Requests\BaseRequest;

class OfferRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'application_id' => ['required', 'uuid', 'exists:applications,id'],
            ...$this->termRules(required: true),
        ];
    }

    protected function rulesForUpdate(): array
    {
        return $this->termRules(required: false);
    }

    private function termRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'position' => [$presence, 'string', 'max:200'],
            'department' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'employment_type' => [$presence, 'in:full-time,part-time,contract,internship'],
            'base_salary' => [$presence, 'integer', 'min:0'],
            'allowances' => ['nullable', 'array', 'max:20'],
            'allowances.*.name' => ['required', 'string', 'max:100'],
            'allowances.*.amount' => ['required', 'integer', 'min:0'],
            'start_date' => [$presence, 'date'],
            'expires_at' => [$presence, 'date', 'after_or_equal:today'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
