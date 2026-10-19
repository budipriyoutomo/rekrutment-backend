<?php

namespace App\Domains\Preboarding\Resources;

use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representasi pre-boarding untuk kandidat di halaman bertoken. */
class PublicPreboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'candidateName' => $this->candidateName(),
            'companyName' => CompanySetting::get('company_name') ?: config('app.name'),
            'position' => $this->position(),
            'startDate' => $this->offer?->start_date?->format('Y-m-d'),
            'status' => $this->status,
            'editable' => $this->isEditableByCandidate(),
            'data' => $this->data ?? (object) [],
            'documents' => collect(PreboardingResource::documentList($this->resource))
                ->map(fn (array $d) => collect($d)->only(['type', 'label', 'uploaded', 'fileName', 'status', 'note'])->all())
                ->all(),
            'expiresAt' => $this->token_expires_at,
        ];
    }
}
