<?php

namespace App\Domains\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTaskTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department' => $this->department,
            'title' => $this->title,
            'description' => $this->description,
            'pic' => $this->pic,
            'offsetDays' => $this->offset_days,
            'sortOrder' => $this->sort_order,
            'isActive' => $this->is_active,
        ];
    }
}
