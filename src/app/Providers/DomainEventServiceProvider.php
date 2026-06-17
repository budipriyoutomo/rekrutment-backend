<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Domains\Application\Events\ApplicationBundlingRequested;
use App\Infrastructure\Listeners\DispatchApplicationBundlingJob;

class DomainEventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        Event::listen(
            ApplicationBundlingRequested::class,
            DispatchApplicationBundlingJob::class
        );
    }
}
