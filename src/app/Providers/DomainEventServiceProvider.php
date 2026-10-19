<?php

namespace App\Providers;

use App\Domains\Application\Events\ApplicationBundlingRequested;
use App\Infrastructure\Listeners\DispatchApplicationBundlingJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
