<?php

namespace App\Domains\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnboardingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'employeeId'  => $this->employee_id,
            'templateId'  => $this->template_id,
            'title'       => $this->title,
            'description' => $this->description,
            'pic'         => $this->pic,
            'dueDate'     => $this->due_date?->format('Y-m-d'),
            'completed'   => $this->completed_at !== null,
            'completedAt' => $this->completed_at,
            'overdue'     => $this->isOverdue(),
            'notes'       => $this->notes,
        ];
    }
}
