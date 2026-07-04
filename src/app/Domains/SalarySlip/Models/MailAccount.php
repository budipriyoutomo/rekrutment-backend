<?php

namespace App\Domains\SalarySlip\Models;

use App\Core\Models\BaseModel;
use App\Traits\HasUuid;
use App\Traits\HasUserstamps;

class MailAccount extends BaseModel
{
    use HasUuid, HasUserstamps;

    protected $table = 'mail_accounts';

    protected $fillable = [
        'label',
        'driver',
        'from_email',
        'from_name',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password_encrypted',
        'smtp_encryption',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        // dekripsi hanya saat runtime; tersimpan terenkripsi di DB
        'smtp_password_encrypted' => 'encrypted',
        'smtp_port'               => 'integer',
        'is_default'              => 'boolean',
        'is_active'               => 'boolean',
    ];

    // Jangan pernah bocorkan password ke response API / log
    protected $hidden = [
        'smtp_password_encrypted',
    ];

    public function slips()
    {
        return $this->hasMany(SalarySlip::class, 'mail_account_id');
    }
}
