<?php

namespace App\Domains\Application\Models;

use App\Core\Models\BaseModel;

class ApplicationCertification extends BaseModel
{
    protected $table = 'application_certifications';

    protected $fillable = [
        'application_id',
        'course_name',
        'organization',
        'year',
        'duration',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}