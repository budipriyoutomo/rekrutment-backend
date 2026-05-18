<?php

namespace App\Domains\Application\Models;

use App\Core\Models\BaseModel;
use App\Domains\Interview\Models\Interview;

class Application extends BaseModel
{
    protected $table = 'applications';

    protected $casts = [
        'personal_info' => 'array',
        'contact_info' => 'array',
        'parent_info' => 'array',
        'spouse_info' => 'array',
        'additional_info' => 'array',
        'documents' => 'array',
        'notes' => 'array',
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

    public function interviews()
    {
        return $this->hasMany(Interview::class, 'applicant_id');
    }

     /*
    |--------------------------------------------------------------------------
    | HELPERS (OPTIONAL TAPI BAGUS)
    |--------------------------------------------------------------------------
    */

    public function getDocument(string $type): ?array
    {
        return $this->documents[$type] ?? null;
    }

    public function hasDocument(string $type): bool
    {
        return isset($this->documents[$type]);
    }
}
