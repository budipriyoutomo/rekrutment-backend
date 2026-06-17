<?php

namespace App\Domains\Application\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationBundlingRequested
{
    use Dispatchable, SerializesModels;

    /**
     * ID Pelamar yang dokumennya ingin digabungkan.
     */
    public string $applicationId;

    /**
     * Create a new event instance.
     */
    public function __construct(string $applicationId)
    {
        $this->applicationId = $applicationId;
    }
}