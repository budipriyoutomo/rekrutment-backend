<?php

namespace App\Domains\Interviewer\Resources;

use App\Core\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class InterviewerResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->formatArray([
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'position' => $this->position,
            'department' => $this->department,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => (bool) $this->active,
            'is_active' => (bool) $this->active,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ]);
    }
}
