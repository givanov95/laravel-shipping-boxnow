<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow;

use Givanov95\Shipping\BoxNow\Exceptions\BoxNowException;
use Givanov95\Shipping\BoxNow\Requests\AuthSessionRequest;
use Givanov95\Shipping\BoxNow\Requests\CreateDeliveryRequest;
use Givanov95\Shipping\BoxNow\Requests\GetLockersRequest;
use Givanov95\TypedHttp\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BoxNow
{
    private const TOKEN_CACHE_KEY = 'boxnow-access-token';

    public function __construct(private readonly ?BoxNowConnector $connector = null)
    {
    }

    public function getAccessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $body = $this->send(new AuthSessionRequest());
        } catch (Throwable $e) {
            throw new BoxNowException('Failed to obtain BoxNow access token: '.$e->getMessage(), 0, $e);
        }

        $token = $body?->access_token ?? null;
        $expiresIn = (int) ($body?->expires_in ?? 3600);

        if (! is_string($token) || $token === '') {
            throw new BoxNowException('BoxNow auth response missing access_token');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 60)));

        return $token;
    }

    public function createDelivery(CreateDeliveryRequest $request)
    {
        return $this->send($request);
    }

    public function getLockers(GetLockersRequest $request)
    {
        return $this->send($request);
    }

    private function send(Request $request): mixed
    {
        return ($this->connector ?? new BoxNowConnector())->send($request)->object();
    }
}
