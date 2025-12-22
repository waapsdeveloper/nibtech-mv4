<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\VariationStockUpdated::class => [
            \App\Listeners\DistributeStockToMarketplaces::class,
        ],
        // V2 Events (generic, uses MarketplaceAPIService)
        // Keep ONLY V2 listeners here to avoid double-processing (double lock / double stock reduce).
        \App\Events\V2\OrderCreated::class => [
            \App\Listeners\V2\LockStockOnOrderCreated::class,
        ],
        \App\Events\V2\OrderStatusChanged::class => [
            \App\Listeners\V2\ReduceStockOnOrderCompleted::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
