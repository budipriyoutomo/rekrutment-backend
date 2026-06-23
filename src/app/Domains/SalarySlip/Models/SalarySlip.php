<?php

namespace App\Domains\SalarySlip\Models;

use App\Core\Models\BaseModel;
use App\Traits\HasUuid;
use App\Traits\HasUserstamps;

class SalarySlip extends BaseModel
{
    use HasUuid, HasUserstamps;

    protected $table = 'salary_slips';

    protected $fillable = [
        'nik',
        'nama',
        'jabatan',
        'periode',
        'cabang',
        'perusahaan',
        'take_home_pay',
        'email',
        'sent_at',
    ];

    protected $casts = [
        'take_home_pay' => 'integer',
        'sent_at'       => 'datetime',
    ];
}
