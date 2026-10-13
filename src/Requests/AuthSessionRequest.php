<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Requests;

use Givanov95\TypedHttp\Enums\BodyFormat;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;

class AuthSessionRequest extends Request
{
    public string $grant_type;

    public string $client_id;

    public string $client_secret;

    public function __construct()
    {
        $this->grant_type = 'client_credentials';
        $this->client_id = (string) config('boxnow.client_id');
        $this->client_secret = (string) config('boxnow.client_secret');
    }

    public function method(): HttpMethod
    {
        return HttpMethod::POST;
    }

    /**
     * The auth endpoint is a separate absolute URL, not under the API base URL.
     */
    public function endpoint(): string
    {
        return (string) config('boxnow.auth_url');
    }

    public function bodyFormat(): BodyFormat
    {
        return BodyFormat::Form;
    }
}
