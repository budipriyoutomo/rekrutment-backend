<?php

namespace App\Domains\Employee\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;
use App\Domains\Offer\Models\Offer;
use App\Domains\Preboarding\Models\Preboarding;

class Employee extends BaseModel
{
    public const STATUS_ONBOARDING = 'onboarding';

    public const STATUS_ACTIVE = 'active';

    protected $table = 'employees';

    protected $casts = [
        'personal_data' => 'array',
        'start_date' => 'date:Y-m-d',
        'welcome_sent_at' => 'datetime',
        'onboarded_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function preboarding()
    {
        return $this->belongsTo(Preboarding::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class)->orderBy('due_date')->orderBy('sort_order');
    }
}
