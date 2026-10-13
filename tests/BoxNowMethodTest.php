<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Tests;

use Givanov95\Shipping\BoxNow\BoxNowMethod;
use Givanov95\Shipping\BoxNow\Tests\Fixtures\Basket;
use Givanov95\Shipping\Contracts\DeliveryCountries;
use Givanov95\Shipping\Contracts\PlaceDirectory;
use Givanov95\Shipping\DeliveryMethods;
use Givanov95\Shipping\DestinationType;
use Givanov95\Shipping\Place;
use Givanov95\Shipping\Point;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * BoxNow behind the delivery contract: the locker is picked by city from the list BoxNow
 * publishes. The list is faked; no credentials are set.
 */
final class BoxNowMethodTest extends TestCase
{
    private const LOCKERS = [
        ['id' => '1840', 'name' => 'BOX NOW - New Avenue', 'addressLine1' => 'ул. Проф. Храбър Попов 14', 'addressLine2' => 'София', 'postalCode' => '1700'],
        ['id' => '12', 'name' => 'BOX NOW - Mall', 'addressLine1' => 'бул. Черни връх 100', 'addressLine2' => 'София', 'postalCode' => '1407'],
        ['id' => '305', 'name' => 'BOX NOW - Sea Garden', 'addressLine1' => 'бул. Приморски 1', 'addressLine2' => 'Варна', 'postalCode' => '9000'],
        // Not usable: without an id it cannot be ordered to, without a city it cannot be found.
        ['name' => 'BOX NOW - No id', 'addressLine2' => 'София'],
        ['id' => '999', 'name' => 'BOX NOW - Nowhere', 'addressLine2' => ''],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'boxnow.locations_url' => 'https://locations.boxnow.test/v1/apms.json',
            'boxnow.prices'        => ['BGN' => '4.99'],
            'boxnow.country'       => 'bg',
        ]);
    }

    private function fakeLockers(): void
    {
        Http::fake(['locations.boxnow.test/*' => Http::response(['data' => self::LOCKERS])]);
    }

    private function method(): BoxNowMethod
    {
        return $this->app->make(DeliveryMethods::class)->get('box_now');
    }

    /**
     * @param  array<string, mixed>  $choice
     */
    private function refused(array $choice): array
    {
        return Validator::make($choice, $this->method()->rules(DestinationType::Locker))->errors()->toArray();
    }

    public function test_the_package_registers_the_method_under_the_key_orders_keep(): void
    {
        $methods = $this->app->make(DeliveryMethods::class);

        $this->assertTrue($methods->has('box_now'));
        $this->assertSame(['box_now' => 'BoxNow'], $methods->labels());
        $this->assertInstanceOf(BoxNowMethod::class, $methods->get('box_now'));
        $this->assertInstanceOf(PlaceDirectory::class, $methods->get('box_now'));
        $this->assertInstanceOf(DeliveryCountries::class, $methods->get('box_now'));
        $this->assertSame([DestinationType::Locker], $methods->get('box_now')->destinationTypes());
    }

    public function test_the_configuration_comes_with_the_package(): void
    {
        $defaults = require __DIR__.'/../config/boxnow.php';

        $this->assertSame('BG', $defaults['country']);
        $this->assertSame('https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json', $defaults['locations_url']);
        $this->assertSame(['EUR' => '4.99'], $defaults['prices']);
        $this->assertSame('https://locations.boxnow.test/v1/apms.json', config('boxnow.locations_url'));
    }

    public function test_the_cities_with_a_locker_are_listed_the_ones_with_the_most_lockers_first(): void
    {
        $this->fakeLockers();

        $this->assertEquals([new Place('София', 'София'), new Place('Варна', 'Варна')], $this->method()->places(''));
        $this->assertEquals([new Place('Варна', 'Варна')], $this->method()->places('вар'));
    }

    public function test_the_lockers_of_a_city_are_listed_by_name(): void
    {
        $this->fakeLockers();

        $this->assertEquals(
            [new Point('12', 'BOX NOW - Mall'), new Point('1840', 'BOX NOW - New Avenue')],
            $this->method()->points('София', DestinationType::Locker),
        );

        // BoxNow has lockers only, and a locker has no streets to choose from.
        $this->assertSame([], $this->method()->points('София', DestinationType::Office));
        $this->assertSame([], $this->method()->streets('София', ''));
    }

    /**
     * As in the list BoxNow publishes: "Варнa" ends in a Latin letter, "София " in a space.
     */
    public function test_a_city_typed_with_a_latin_letter_or_a_space_is_not_a_city_of_its_own(): void
    {
        Http::fake(['locations.boxnow.test/*' => Http::response(['data' => [
            ...self::LOCKERS,
            ['id' => '306', 'name' => 'BOX NOW - Port', 'addressLine2' => "Варн\u{0061}", 'postalCode' => '9000'],
            ['id' => '13', 'name' => 'BOX NOW - Station', 'addressLine2' => 'София ', 'postalCode' => '1000'],
            ['id' => '700', 'name' => 'BOX NOW - Abroad', 'addressLine2' => 'Thessaloniki'],
        ]])]);

        $this->assertEquals(
            [new Place('София', 'София'), new Place('Варна', 'Варна'), new Place('Thessaloniki', 'Thessaloniki')],
            $this->method()->places(''),
        );
        $this->assertEquals(
            [new Point('306', 'BOX NOW - Port'), new Point('305', 'BOX NOW - Sea Garden')],
            $this->method()->points('Варна', DestinationType::Locker),
        );
    }

    public function test_the_list_is_read_once_without_credentials_and_cached(): void
    {
        $this->fakeLockers();

        $this->method()->places('');
        $this->assertCount(1, $this->method()->points('Варна', DestinationType::Locker));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://locations.boxnow.test/v1/apms.json'
            && $request->method() === 'GET'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_a_failing_boxnow_gives_empty_lists_and_is_not_cached(): void
    {
        Http::fakeSequence('locations.boxnow.test/*')
            ->push('Service Unavailable', 503)
            ->push(['data' => self::LOCKERS]);

        $this->assertSame([], $this->method()->places(''));
        $this->assertCount(2, $this->method()->places(''));
    }

    public function test_only_a_locker_of_the_list_can_be_chosen(): void
    {
        $this->fakeLockers();

        $this->assertSame([], $this->refused(['point_code' => '1840']));
        $this->assertSame(['point_code' => ['The selected locker is not available.']], $this->refused(['point_code' => 'L-100']));
        $this->assertSame(['point_code'], array_keys($this->refused(['point_code' => null])));
    }

    public function test_no_locker_can_be_chosen_while_boxnow_gives_no_list(): void
    {
        Http::fake(['locations.boxnow.test/*' => Http::response('Service Unavailable', 503)]);

        $this->assertSame(['point_code'], array_keys($this->refused(['point_code' => '1840'])));
    }

    public function test_the_refusal_is_translated_by_the_package(): void
    {
        $this->fakeLockers();

        foreach (['bg', 'bg-BG'] as $locale) {
            $this->app->setLocale($locale);

            $this->assertSame(['point_code' => ['Избраният автомат не е достъпен.']], $this->refused(['point_code' => 'L-100']));
        }
    }

    public function test_the_destination_is_what_the_list_says_of_the_locker(): void
    {
        $this->fakeLockers();

        // The name and the city of the locker are not taken from the form.
        $destination = $this->method()->destination(DestinationType::Locker, [
            'country_code' => 'BG',
            'point_code'   => '1840',
            'city'         => 'Somewhere else',
            'point_name'   => 'Another name',
        ]);

        $this->assertSame(
            [
                'method'        => 'box_now',
                'type'          => 'locker',
                'country_code'  => 'BG',
                'city'          => 'София',
                'postal_code'   => '1700',
                'street'        => null,
                'street_number' => null,
                'note'          => null,
                'point_code'    => '1840',
                'point_name'    => 'BOX NOW - New Avenue',
                'courier_data'  => null,
            ],
            $destination->toArray(),
        );
    }

    public function test_a_locker_is_priced_at_the_flat_price_in_the_currency_of_the_parcel(): void
    {
        $this->fakeLockers();
        $destination = $this->method()->destination(DestinationType::Locker, ['country_code' => 'BG', 'point_code' => '1840']);

        $price = $this->method()->price($destination, new Basket('BGN'));

        $this->assertSame(['4.99', 'BGN'], [(string) $price->getAmount(), $price->getCurrency()->getCurrencyCode()]);
        $this->assertNull($this->method()->price($destination, new Basket('EUR')));
    }

    public function test_every_currency_has_the_price_the_configuration_names_for_it(): void
    {
        config(['boxnow.prices' => ['EUR' => '6.40', 'BGN' => '12.50', 'RON' => '']]);
        $destination = $this->method()->destination(DestinationType::Locker, ['country_code' => 'BG', 'point_code' => 'L-1']);

        $this->assertSame('6.40', (string) $this->method()->price($destination, new Basket('EUR'))->getAmount());
        $this->assertSame('12.50', (string) $this->method()->price($destination, new Basket('BGN'))->getAmount());

        // The price of another currency is not charged as it is, and an empty one is not a price.
        $this->assertNull($this->method()->price($destination, new Basket('USD')));
        $this->assertNull($this->method()->price($destination, new Basket('RON')));
        $this->assertSame([], $this->method()->countries('RON'));
    }

    /**
     * There is no exchange rate: a parcel in a currency BoxNow has no price in is not offered it.
     */
    public function test_boxnow_delivers_in_the_country_of_its_list_for_the_currencies_it_has_a_price_in(): void
    {
        $this->assertSame(['BG'], $this->method()->countries('BGN'));
        $this->assertSame([], $this->method()->countries('EUR'));

        config(['boxnow.prices' => ['BGN' => '4.99', 'EUR' => '2.55'], 'boxnow.country' => 'GR']);
        $this->assertSame(['GR'], $this->method()->countries('EUR'));
    }

    public function test_the_free_delivery_threshold_of_the_shop_holds_for_a_locker(): void
    {
        $this->fakeLockers();

        $this->assertTrue($this->method()->freeDeliveryApplies(
            $this->method()->destination(DestinationType::Locker, ['country_code' => 'BG', 'point_code' => '1840']),
        ));
    }
}
