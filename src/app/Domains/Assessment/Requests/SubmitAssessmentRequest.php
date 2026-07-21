<?php

namespace App\Domains\Assessment\Requests;

use App\Core\Http\Requests\BaseRequest;

class SubmitAssessmentRequest extends BaseRequest
{
    protected function rulesForCreate(): array
    {
        return [
            // Peta question_id => key opsi. Soal yang tidak dijawab boleh absen
            // dari peta ini dan otomatis dihitung salah.
            'answers'   => ['present', 'array'],
            'answers.*' => ['nullable', 'string', 'max:8'],
        ];
    }

    protected function rulesForUpdate(): array
    {
        return [];
    }
}
