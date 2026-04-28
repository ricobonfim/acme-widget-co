<?php

namespace App\Providers;

use App\Services\OfferService;
use App\Services\Offers\Offer;
use App\Services\Offers\RedWidgetBogoHalfPrice;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The list of active offers, in the order they should be evaluated.
     *
     * Adding a new promotion is a two-step process:
     *   1. Create a class implementing App\Services\Offers\Offer.
     *   2. Add its FQCN to this array.
     */
    private const ACTIVE_OFFERS = [
        RedWidgetBogoHalfPrice::class,
    ];

    public function register(): void
    {
        $this->app->singleton(OfferService::class, function ($app) {
            $offers = array_map(
                fn (string $class): Offer => $app->make($class),
                self::ACTIVE_OFFERS,
            );

            return new OfferService($offers);
        });
    }

    public function boot(): void
    {
        //
    }
}
