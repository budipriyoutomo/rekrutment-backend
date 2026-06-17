<?php

namespace App\Infrastructure\Listeners;

use App\Domains\Application\Events\ApplicationBundlingRequested;
use App\Domains\Application\Jobs\ProcessApplicationBundlingJob; 

class DispatchApplicationBundlingJob
{
    /**
     * Handle the event.
     * Listener menangkap event dan bertugas melempar job ke antrean queue.
     */
    public function handle(ApplicationBundlingRequested $event): void
    {
        // Memasukkan job pelamar ke dalam antrean background worker
        ProcessApplicationBundlingJob::dispatch($event->applicationId);
        
        // Opsional: Jika ingin mengarahkan ke queue spesifik
        // ProcessApplicationBundlingJob::dispatch($event->applicationId)->onQueue('documents');
    }
}