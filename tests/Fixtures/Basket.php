<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Tests\Fixtures;

use Givanov95\Shipping\Contracts\Parcel;

final class Basket implements Parcel
{
    public function __construct(private readonly string $currency)
    {
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
