<?php

namespace App\Domains\Application\Models;

use App\Core\Models\BaseModel;

class Application extends BaseModel
{
    protected $table = 'applications';

    protected $casts = [
        'personal_info' => 'array',
        'contact_info' => 'array',
        'parent_info' => 'array',
        'spouse_info' => 'array',
        'additional_info' => 'array',
    ];

    public function educations()
    {
        return $this->hasMany(ApplicationEducation::class);
    }

    public function experiences()
    {
        return $this->hasMany(ApplicationExperience::class);
    }

    public function certifications()
    {
        return $this->hasMany(ApplicationCertification::class);
    }
}