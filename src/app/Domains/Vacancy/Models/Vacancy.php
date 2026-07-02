<?php

namespace App\Domains\Vacancy\Models;

use App\Core\Models\BaseModel;

class Vacancy extends BaseModel
{
    protected $table = 'vacancies';

    protected $casts = [
        'requirements' => 'array',
        'salary'       => 'array',
        'posted_date'  => 'date:Y-m-d',
        'closing_date' => 'date:Y-m-d',
    ];
}
