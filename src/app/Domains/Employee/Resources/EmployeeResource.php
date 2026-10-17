<?php

namespace App\Domains\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : null;

        return [
            'id'             => $this->id,
            'employeeNumber' => $this->employee_number,
            'applicationId'  => $this->application_id,
            'preboardingId'  => $this->preboarding_id,
            'fullName'       => $this->full_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'position'       => $this->position,
            'department'     => $this->department,
            'location'       => $this->location,
            'employmentType' => $this->employment_type,
            'startDate'      => $this->start_date?->format('Y-m-d'),
            'personalData'   => $this->personal_data ?? (object) [],
            'status'         => $this->status,
            'welcomeSentAt'  => $this->welcome_sent_at,
            'onboardedAt'    => $this->onboarded_at,
            'progress'       => $tasks ? [
                'total'     => $tasks->count(),
                'completed' => $tasks->whereNotNull('completed_at')->count(),
                'overdue'   => $tasks->filter->isOverdue()->count(),
            ] : null,
            'tasks'          => OnboardingTaskResource::collection($this->whenLoaded('tasks')),
            'createdAt'      => $this->created_at,
        ];
    }
}
