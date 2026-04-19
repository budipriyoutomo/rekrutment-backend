<?php

namespace App\Domains\Application\Requests;

use App\Core\Http\Requests\BaseRequest;

class SubmitApplicationRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            'personalInfo.fullName' => ['required'],
            'contactInfo.email' => ['required', 'email'],
            'contactInfo.phone' => ['required'],
            'education' => ['array'],
            'workExperience' => ['array'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }
}