<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Requests;

use Givanov95\Shipping\BoxNow\Components\Recipient\Recipient;
use Givanov95\TypedHttp\Auth\BearerToken;
use Givanov95\TypedHttp\Contracts\Authenticator;
use Givanov95\TypedHttp\Enums\HttpMethod;
use Givanov95\TypedHttp\Request;

class CreateDeliveryRequest extends Request
{
    public string $partnerId;

    public string $deliveryLockerId;

    public string $originLockerId;

    public array $recipient;

    public ?string $weight;

    public ?string $contents;

    public ?string $orderNumber;

    public ?float $codAmount;

    public function __construct(
        private readonly string $accessToken,
        string $partnerId,
        string $deliveryLockerId,
        string $originLockerId,
        Recipient $recipient,
        ?string $weight = null,
        ?string $contents = null,
        ?string $orderNumber = null,
        ?float $codAmount = null,
    ) {
        $this->partnerId = $partnerId;
        $this->deliveryLockerId = $deliveryLockerId;
        $this->originLockerId = $originLockerId;
        $this->recipient = $recipient->toArray();
        $this->weight = $weight;
        $this->contents = $contents;
        $this->orderNumber = $orderNumber;
        $this->codAmount = $codAmount;
    }

    /**
     * The access token is a private property, so it stays out of the payload.
     */
    public function authenticator(): Authenticator
    {
        return new BearerToken($this->accessToken);
    }

    public function method(): HttpMethod
    {
        return HttpMethod::POST;
    }

    public function endpoint(): string
    {
        return '/delivery-requests';
    }
}
