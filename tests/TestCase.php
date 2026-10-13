<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Tests;

use Givanov95\Shipping\BoxNow\BoxNowServiceProvider;
use Givanov95\Shipping\ShippingServiceProvider;
use Givanov95\TypedHttp\Laravel\TypedHttpServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * An application with nothing of a shop in it: the delivery contract, the HTTP bridge and
 * this package. Every request to BoxNow is faked.
 */
abstract class TestCase extends Testbench
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [TypedHttpServiceProvider::class, ShippingServiceProvider::class, BoxNowServiceProvider::class];
    }
}
