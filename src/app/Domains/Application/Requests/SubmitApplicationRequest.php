<?php

namespace App\Domains\Application\Requests;

use App\Core\Http\Requests\BaseRequest;
use App\Core\Support\CityPrefix;
use Illuminate\Contracts\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateWorkLocationCity($v));
    }

    /** Lokasi kerja wajib sekota dengan posisi yang dilamar. */
    private function validateWorkLocationCity(Validator $validator): void
    {
        $position = $this->input('additionalInfo.positionApplied');
        $location = $this->input('additionalInfo.workLocation');

        if (! CityPrefix::mismatch($position, $location)) {
            return;
        }

        $validator->errors()->add(
            'additionalInfo.workLocation',
            sprintf(
                'Lokasi kerja harus berada di kota yang sama dengan posisi yang dilamar (%s).',
                CityPrefix::of($position)
            )
        );
    }
}
