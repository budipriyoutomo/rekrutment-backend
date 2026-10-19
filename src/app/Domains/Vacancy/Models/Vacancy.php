<?php

namespace App\Domains\Vacancy\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;
use App\Domains\JobRequest\Models\JobRequest;
use App\Enums\PipelineStage;

class Vacancy extends BaseModel
{
    protected $table = 'vacancies';

    protected $casts = [
        'requirements' => 'array',
        'salary' => 'array',
        'headcount' => 'integer',
        'posted_date' => 'date:Y-m-d',
        'closing_date' => 'date:Y-m-d',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function jobRequest()
    {
        return $this->belongsTo(JobRequest::class);
    }

    /** Jumlah pelamar lowongan ini yang sudah di stage hired. */
    public function hiredCount(): int
    {
        return $this->applications()->where('stage', PipelineStage::HIRED->value)->count();
    }

    /** Kuota terpenuhi? Lowongan tanpa headcount tidak pernah dianggap penuh. */
    public function isHeadcountFilled(): bool
    {
        return $this->headcount !== null && $this->hiredCount() >= $this->headcount;
    }
}
