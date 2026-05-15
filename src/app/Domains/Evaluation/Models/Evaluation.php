<?php

namespace App\Domains\Evaluation\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;

class Evaluation extends BaseModel
{
    protected $table = 'evaluations';

    protected $fillable = [
        'applicant_id',
        'applicant_name',
        'position',
        'evaluator',
        'date',
        'communication_score',
        'technical_score',
        'experience_score',
        'culture_fit_score',
        'recommendation',
        'strengths',
        'improvements',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function applicant()
    {
        return $this->belongsTo(Application::class, 'applicant_id');
    }
}
