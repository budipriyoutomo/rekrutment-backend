<?php

namespace App\Domains\Offer\Resources;

use App\Domains\Setting\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Representasi offer untuk kandidat (halaman publik bertoken) — tanpa data internal. */
class PublicOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'candidateName' => $this->candidateName(),
            'companyName' => CompanySetting::get('company_name') ?: config('app.name'),
            'position' => $this->position,
            'department' => $this->department,
            'location' => $this->location,
            'employmentType' => $this->employment_type,
            'baseSalary' => $this->base_salary,
            'allowances' => $this->allowances ?? [],
            'totalCompensation' => $this->base_salary + $this->totalAllowances(),
            'startDate' => $this->start_date?->format('Y-m-d'),
            'expiresAt' => $this->expires_at?->format('Y-m-d'),
            'terms' => $this->terms,
            'status' => $this->status,
            'respondedAt' => $this->responded_at,
        ];
    }
}
