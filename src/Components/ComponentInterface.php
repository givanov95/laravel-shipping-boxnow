<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Components;

interface ComponentInterface
{
    public function toArray(): array;

    public function validate(): bool;
}
