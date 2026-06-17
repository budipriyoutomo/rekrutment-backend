<?php

namespace App\Domains\ProfileCompletion\Models;

use App\Core\Models\BaseModel;
use App\Domains\Application\Models\Application;

class ProfileCompletionToken extends BaseModel
{
    protected $table = 'profile_completion_tokens';

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
