<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow;

use Givanov95\TypedHttp\Connector;

/**
 * The BoxNow API. It has no connector-level authentication: CreateDeliveryRequest brings the access token
 * it got from BoxNow::getAccessToken(), AuthSessionRequest goes to its own absolute auth URL.
 */
final class BoxNowConnector extends Connector
{
    public function baseUrl(): string
    {
        return (string) config('boxnow.base_url');
    }
}
