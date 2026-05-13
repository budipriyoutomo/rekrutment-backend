<?php

namespace App\Domains\Interviewer\Models;

use App\Core\Models\BaseModel;

class Interviewer extends BaseModel
{
    protected $table = 'interviewers';

    protected $fillable = [
        'name',
        'role',
        'position',
        'department',
        'email',
        'phone',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
