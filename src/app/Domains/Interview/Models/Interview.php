<?php

namespace App\Domains\Interview\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;

class Interview extends BaseModel
{
    protected $table = 'interviews';

    protected $fillable = [
        'applicant_id',
        'applicant_name',
        'position',
        'date',
        'time',
        'duration',
        'type',
        'interviewers',
        'status',
        'room',
        'notes',
        'email_sent',
        'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'interviewers' => 'array',
            'email_sent' => 'boolean',
            'email_sent_at' => 'datetime',
        ];
    }

    public function applicant()
    {
        return $this->belongsTo(Application::class, 'applicant_id');
    }
}
