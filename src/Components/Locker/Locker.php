<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Components\Locker;

use Givanov95\Shipping\BoxNow\Components\Address\Address;
use Givanov95\Shipping\BoxNow\Components\BaseComponent;

class Locker extends BaseComponent
{
    public ?string $id;

    public ?string $name;

    public ?string $type;

    public ?string $status;

    public ?Address $address;

    public ?array $location;

    public ?int $boxCount;

    public ?int $boxFreeCount;

    public ?string $nameEn;

    public ?string $partnerId;

    public function __construct(
        ?string $id = null,
        ?string $name = null,
        ?string $type = null,
        ?string $status = null,
        ?Address $address = null,
        ?array $location = null,
        ?int $boxCount = null,
        ?int $boxFreeCount = null,
        ?string $nameEn = null,
        ?string $partnerId = null,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->status = $status;
        $this->address = $address;
        $this->location = $location;
        $this->boxCount = $boxCount;
        $this->boxFreeCount = $boxFreeCount;
        $this->nameEn = $nameEn;
        $this->partnerId = $partnerId;
    }

    public function validate(): bool
    {
        return $this->id !== null;
    }
}
