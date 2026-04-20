<?php

namespace App\Domains\Application\Models;

use App\Core\Models\BaseModel;

class ApplicationExperience extends BaseModel
{
    protected $table = 'application_experiences';

    protected $fillable = [
        'application_id',
        'company_name',
        'job_position',
        'year_start',
        'year_end',
        'job_description',
        'restaurant_industry',
        'restaurant_type',
        'position_category',
        'responsibilities',
        'pos_experience',
        'pos_system',
        'shifts',
        'team_size',
        'reason_for_leaving',
    ];

    protected $casts = [
        'responsibilities' => 'array',
        'shifts' => 'array',
        'restaurant_type' => 'array',
        'pos_experience' => 'array',
        'pos_system' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}