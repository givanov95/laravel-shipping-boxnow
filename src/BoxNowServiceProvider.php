<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow;

use Givanov95\Shipping\DeliveryMethods;
use Illuminate\Support\ServiceProvider;

final class BoxNowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/boxnow.php', 'boxnow');

        // Registered is not yet offered: the shop names the methods it offers.
        $this->callAfterResolving(DeliveryMethods::class, function (DeliveryMethods $methods): void {
            $methods->register('box_now', BoxNowMethod::class, 'BoxNow');
        });
    }

    public function boot(): void
    {
        // The one text of the method a customer reads; a shop overrides it in its own lang files.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/boxnow.php' => config_path('boxnow.php')], 'boxnow-config');
        }
    }
}
