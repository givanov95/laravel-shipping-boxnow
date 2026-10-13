<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Components\Recipient;

use Givanov95\Shipping\BoxNow\Components\BaseComponent;

class Recipient extends BaseComponent
{
    public ?string $name;

    public ?string $phoneNumber;

    public ?string $email;

    public function __construct(?string $name = null, ?string $phoneNumber = null, ?string $email = null)
    {
        $this->name = $name;
        $this->phoneNumber = $phoneNumber;
        $this->email = $email;
    }

    public function validate(): bool
    {
        return $this->name !== null && $this->phoneNumber !== null;
    }
}
