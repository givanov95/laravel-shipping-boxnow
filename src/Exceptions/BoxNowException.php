<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Exceptions;

use Exception;
use Throwable;

class BoxNowException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
