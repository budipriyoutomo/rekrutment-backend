<?php

namespace App\Domains\Application\Requests;

use App\Core\Http\Requests\Concerns\DecodesJsonPayload;

/**
 * Quick Apply (/apply) dikirim sebagai satu request multipart: data lamaran
 * sebagai JSON di field `payload`, plus file `cv` & `foto` yang wajib ada.
 * Dengan begitu lamaran tidak pernah tersimpan tanpa dokumen.
 */
class SubmitQuickApplyRequest extends SubmitApplicationRequest
{
    use DecodesJsonPayload;

    public const MAX_FILE_KB = 2048;

    protected function rulesForCreate(): array
    {
        return array_merge(parent::rulesForCreate(), [
            'payload' => ['required', 'json'],
            'cv' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:pdf,doc,docx'],
            'foto' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:jpg,jpeg,png'],
        ]);
    }

    public function messages(): array
    {
        return [
            'cv.required' => 'CV wajib diunggah.',
            'cv.max' => 'Ukuran CV maksimal 2 MB.',
            'cv.mimes' => 'CV harus berformat PDF, DOC, atau DOCX.',
            'foto.required' => 'Foto wajib diunggah.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
            'foto.mimes' => 'Foto harus berformat JPG atau PNG.',
        ];
    }
}
