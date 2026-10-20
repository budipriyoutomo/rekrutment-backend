<?php

namespace App\Domains\ProfileCompletion\Requests;

use App\Core\Http\Requests\Concerns\DecodesJsonPayload;
use App\Domains\Application\Requests\SubmitApplicationRequest;
use Illuminate\Http\UploadedFile;

/**
 * Kirim form lengkap dari link profile completion: JSON di `payload` + file
 * dokumen. File di sini opsional — yang sudah ada di lamaran (mis. CV & foto
 * dari Quick Apply) tidak perlu diunggah ulang. Kewajiban CV/foto/ijazah
 * dicek di action terhadap dokumen yang sudah tersimpan.
 *
 * Batas ukuran disamakan dengan batas di form (StepDocuments).
 */
class CompleteProfileRequest extends SubmitApplicationRequest
{
    use DecodesJsonPayload;

    /** Batas ukuran per dokumen (KB). */
    public const MAX_KB = ['cv' => 5120, 'foto' => 2048, 'ktp' => 3072, 'ijazah' => 5120];

    protected function rulesForCreate(): array
    {
        return array_merge(parent::rulesForCreate(), [
            'payload' => ['required', 'json'],
            'cv' => ['nullable', 'file', 'max:'.self::MAX_KB['cv'], 'mimes:pdf,doc,docx'],
            'foto' => ['nullable', 'file', 'max:'.self::MAX_KB['foto'], 'mimes:jpg,jpeg,png'],
            'ktp' => ['nullable', 'file', 'max:'.self::MAX_KB['ktp'], 'mimes:jpg,jpeg,png,pdf'],
            'ijazah' => ['nullable', 'file', 'max:'.self::MAX_KB['ijazah'], 'mimes:jpg,jpeg,png,pdf'],
        ]);
    }

    public function messages(): array
    {
        return [
            'cv.max' => 'Ukuran CV maksimal 5 MB.',
            'cv.mimes' => 'CV harus berformat PDF, DOC, atau DOCX.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
            'foto.mimes' => 'Foto harus berformat JPG atau PNG.',
            'ktp.max' => 'Ukuran KTP maksimal 3 MB.',
            'ktp.mimes' => 'KTP harus berformat JPG, PNG, atau PDF.',
            'ijazah.max' => 'Ukuran ijazah maksimal 5 MB.',
            'ijazah.mimes' => 'Ijazah harus berformat JPG, PNG, atau PDF.',
        ];
    }

    /** @return array<string, UploadedFile|null> */
    public function documentFiles(): array
    {
        return [
            'cv' => $this->file('cv'),
            'foto' => $this->file('foto'),
            'ktp' => $this->file('ktp'),
            'ijazah' => $this->file('ijazah'),
        ];
    }
}
