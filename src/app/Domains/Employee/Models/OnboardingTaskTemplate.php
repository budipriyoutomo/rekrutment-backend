<?php

namespace App\Domains\Employee\Models;

use App\Core\Models\BaseModel;

class OnboardingTaskTemplate extends BaseModel
{
    protected $table = 'onboarding_task_templates';

    protected $casts = [
        'offset_days' => 'integer',
        'sort_order'  => 'integer',
        'is_active'   => 'boolean',
    ];

    /** Template aktif untuk sebuah departemen: yang umum (department null) + yang khusus departemen itu. */
    public function scopeApplicableTo($query, ?string $department)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('department')->when($department, fn ($q) => $q->orWhere('department', $department)))
            ->orderBy('offset_days')
            ->orderBy('sort_order');
    }
}
