<?php

namespace App\Domains\Application\Models;

use App\Core\Models\BaseModel;

class ApplicationEducation extends BaseModel
{
    protected $table = 'application_educations';

    protected $fillable = [
        'application_id',
        'level',
        'school_name',
        'city',
        'year_start',
        'year_end',
        'major',
        'gpa',
        'certificate',
    ];

    protected $casts = [
        'year_start' => 'integer',
        'year_end' => 'integer',
        'gpa' => 'float',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}