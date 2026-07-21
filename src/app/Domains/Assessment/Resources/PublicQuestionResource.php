<?php

namespace App\Domains\Assessment\Resources;

use App\Core\Http\Resources\BaseResource;

/**
 * Soal versi kandidat. Sengaja TIDAK memuat correct_answer maupun bobot skor —
 * endpoint pengerjaan tes bersifat publik (hanya berbekal token), sehingga
 * apa pun yang dikirim di sini bisa dibaca kandidat lewat network tab.
 */
class PublicQuestionResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id'       => $this->id,
            'question' => $this->question,
            'options'  => collect($this->options ?? [])
                ->map(fn ($option) => [
                    'key'  => $option['key'] ?? null,
                    'text' => $option['text'] ?? null,
                ])
                ->all(),
            'order'    => (int) $this->order,
        ]);
    }
}
