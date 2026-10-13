<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Requests;

use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;

/**
 * The list of the lockers. BoxNow publishes it as a file that needs no credentials.
 */
class GetLockersRequest extends Request
{
    public function method(): HttpMethod
    {
        return HttpMethod::GET;
    }

    /**
     * The file is on its own host, not under the API base URL.
     */
    public function endpoint(): string
    {
        return (string) config('boxnow.locations_url');
    }
}
