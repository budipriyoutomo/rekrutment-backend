<?php

namespace App\Domains\SalarySlip\Models;

use App\Core\Models\BaseModel;
use App\Traits\HasUuid;

class SendJob extends BaseModel
{
    use HasUuid;

    protected $table = 'send_jobs';

    // Status per job pengiriman
    public const STATUS_QUEUED     = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS    = 'success';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'salary_slip_id',
        'mail_account_id',
        'job_batch_id',
        'status',
        'attempt',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'attempt'     => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function slip()
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }

    public function mailAccount()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
