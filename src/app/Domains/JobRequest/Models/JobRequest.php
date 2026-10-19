<?php

namespace App\Domains\JobRequest\Models;

use App\Core\Models\BaseModel;
use App\Domains\Vacancy\Models\Vacancy;

class JobRequest extends BaseModel
{
    protected $table = 'job_requests';

    protected $fillable = [
        'title',
        'department',
        'location',
        'employment_type',
        'headcount',
        'salary_range',
        'needed_by',
        'justification',
        'requirements',
        'requested_by',
        'priority',
        'status',
        'reviewer_notes',
    ];

    public function vacancy()
    {
        return $this->hasOne(Vacancy::class);
    }

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'headcount' => 'integer',
        ];
    }
}
