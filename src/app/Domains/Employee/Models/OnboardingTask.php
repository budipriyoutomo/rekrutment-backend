<?php

namespace App\Domains\Employee\Models;

use App\Core\Models\BaseModel;

class OnboardingTask extends BaseModel
{
    protected $table = 'onboarding_tasks';

    protected $casts = [
        'due_date'     => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->due_date->lt(now()->startOfDay());
    }
}
