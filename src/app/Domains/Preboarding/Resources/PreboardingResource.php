<?php

namespace App\Domains\Preboarding\Resources;

use App\Domains\Preboarding\Models\Preboarding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representasi pre-boarding untuk HR (tanpa path file; unduh lewat endpoint terautentikasi). */
class PreboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'applicationId' => $this->application_id,
            'offerId' => $this->offer_id,
            'employeeId' => $this->whenLoaded('employee', fn () => $this->employee?->id),
            'candidateName' => $this->candidateName(),
            'candidateEmail' => $this->candidateEmail(),
            'position' => $this->position(),
            'startDate' => $this->offer?->start_date?->format('Y-m-d'),
            'status' => $this->status,
            'data' => $this->data ?? (object) [],
            'documents' => self::documentList($this->resource),
            'publicUrl' => $this->publicUrl(),
            'tokenExpiresAt' => $this->token_expires_at,
            'sentAt' => $this->sent_at,
            'submittedAt' => $this->submitted_at,
            'verifiedAt' => $this->verified_at,
            'createdAt' => $this->created_at,
        ];
    }

    /** Daftar dokumen wajib beserta status unggah/verifikasinya, tanpa path penyimpanan. */
    public static function documentList(Preboarding $preboarding): array
    {
        return collect(Preboarding::REQUIRED_DOCUMENTS)
            ->map(function (string $label, string $type) use ($preboarding) {
                $doc = $preboarding->document($type);

                return [
                    'type' => $type,
                    'label' => $label,
                    'uploaded' => ! empty($doc['path']),
                    'fileName' => $doc['file_name'] ?? null,
                    'mimeType' => $doc['mime_type'] ?? null,
                    'size' => $doc['size'] ?? null,
                    'status' => $doc['status'] ?? null,
                    'note' => $doc['note'] ?? null,
                    'uploadedAt' => $doc['uploaded_at'] ?? null,
                    'reviewedAt' => $doc['reviewed_at'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
