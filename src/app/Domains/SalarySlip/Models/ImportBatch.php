<?php

namespace App\Domains\SalarySlip\Models;

use App\Core\Models\BaseModel;
use App\Traits\HasUuid;

class ImportBatch extends BaseModel
{
    use HasUuid;

    protected $table = 'import_batches';

    protected $fillable = [
        'id',
        'file_name',
        'total_rows',
        'success_rows',
        'failed_rows',
        'errors',
        'uploaded_by',
    ];

    protected $casts = [
        'errors'       => 'array',
        'total_rows'   => 'integer',
        'success_rows' => 'integer',
        'failed_rows'  => 'integer',
    ];

    public function slips()
    {
        return $this->hasMany(SalarySlip::class, 'batch_id');
    }
}
