<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Components\Address;

use Givanov95\Shipping\BoxNow\Components\BaseComponent;

class Address extends BaseComponent
{
    public ?string $country;

    public ?string $city;

    public ?string $postalCode;

    public ?string $street;

    public ?string $number;

    public ?string $note;

    public function __construct(
        ?string $country = 'BG',
        ?string $city = null,
        ?string $postalCode = null,
        ?string $street = null,
        ?string $number = null,
        ?string $note = null,
    ) {
        $this->country = $country;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->street = $street;
        $this->number = $number;
        $this->note = $note;
    }

    public function validate(): bool
    {
        return $this->city !== null;
    }
}
