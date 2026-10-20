<?php

namespace App\Core\Http\Requests\Concerns;

/**
 * Untuk request multipart yang membawa data terstruktur sebagai JSON di field
 * `payload` (berdampingan dengan file). Isi payload digabung ke input sebelum
 * validasi sehingga aturan seperti `personalInfo.fullName` tetap berlaku.
 */
trait DecodesJsonPayload
{
    protected function prepareForValidation()
    {
        $payload = json_decode((string) $this->input('payload'), true);

        if (is_array($payload)) {
            $this->merge($payload);
        }

        parent::prepareForValidation();
    }
}
