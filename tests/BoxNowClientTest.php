<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Tests;

use Givanov95\Shipping\BoxNow\BoxNow;
use Givanov95\Shipping\BoxNow\Components\Recipient\Recipient;
use Givanov95\Shipping\BoxNow\Exceptions\BoxNowException;
use Givanov95\Shipping\BoxNow\Requests\CreateDeliveryRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class BoxNowClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'boxnow.base_url'      => 'https://boxnow.test/api/v1',
            'boxnow.auth_url'      => 'https://boxnow.test/api/v1/auth-sessions',
            'boxnow.client_id'     => 'client',
            'boxnow.client_secret' => 'secret',
        ]);
    }

    public function test_the_access_token_is_requested_as_a_form_and_cached(): void
    {
        Http::fake(['boxnow.test/*' => Http::response(['access_token' => 'tok-1', 'expires_in' => 3600])]);
        $boxNow = new BoxNow();

        $this->assertSame('tok-1', $boxNow->getAccessToken());
        $this->assertSame('tok-1', $boxNow->getAccessToken());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://boxnow.test/api/v1/auth-sessions'
            && $request->method() === 'POST'
            && $request->header('Content-Type') === ['application/x-www-form-urlencoded']
            && ! $request->hasHeader('Authorization')
            && $request->body() === 'grant_type=client_credentials&client_id=client&client_secret=secret');
    }

    public function test_a_response_without_a_token_raises_a_box_now_exception(): void
    {
        Http::fake(['boxnow.test/*' => Http::response(['expires_in' => 3600])]);

        $this->expectException(BoxNowException::class);
        $this->expectExceptionMessage('missing access_token');

        (new BoxNow())->getAccessToken();
    }

    public function test_an_auth_failure_is_wrapped_in_a_box_now_exception(): void
    {
        Http::fake(['boxnow.test/*' => Http::response(['error' => 'invalid_client'], 401)]);

        try {
            (new BoxNow())->getAccessToken();
            $this->fail('BoxNowException expected');
        } catch (BoxNowException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertInstanceOf(\Givanov95\TypedHttp\Exceptions\RequestException::class, $e->getPrevious());
        }
    }

    public function test_a_delivery_is_created_with_the_bearer_token_and_a_json_body(): void
    {
        Http::fake(['boxnow.test/*' => Http::response(['parcels' => [['id' => '77']]])]);

        $response = (new BoxNow())->createDelivery(new CreateDeliveryRequest(
            accessToken: 'tok-1',
            partnerId: 'p1',
            deliveryLockerId: 'L2',
            originLockerId: 'L1',
            recipient: new Recipient(name: 'John Doe', phoneNumber: '+359888888888'),
            orderNumber: 'A-1',
        ));

        $this->assertSame('77', $response->parcels[0]->id);
        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $request->url() === 'https://boxnow.test/api/v1/delivery-requests'
                && $request->header('Authorization') === ['Bearer tok-1']
                && $request->header('Content-Type') === ['application/json']
                && $data['partnerId'] === 'p1'
                && $data['deliveryLockerId'] === 'L2'
                && $data['orderNumber'] === 'A-1'
                && ! array_key_exists('accessToken', $data);
        });
    }
}
