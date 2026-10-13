<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow\Tests;

use Givanov95\Shipping\BoxNow\BoxNowMethod;
use Givanov95\Shipping\BoxNow\BoxNowService;
use Givanov95\Shipping\DeliveryMethods;
use Givanov95\Shipping\DestinationType;
use Illuminate\Support\Facades\Http;

/**
 * The list as BoxNow publishes it: 18 of its rows, taken unchanged on 2026-10-13 from
 * https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json (tests/Fixtures/apms_bg-BG.json).
 * They are the ones the list writes in a way of its own.
 */
final class PublishedListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['boxnow.locations_url' => 'https://locations.boxnow.test/v1/apms.json']);
        Http::fake(['locations.boxnow.test/*' => Http::response(
            (string) file_get_contents(__DIR__.'/Fixtures/apms_bg-BG.json'),
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
        )]);
    }

    private function method(): BoxNowMethod
    {
        return $this->app->make(DeliveryMethods::class)->get('box_now');
    }

    /**
     * @return array<string, array{id: string, name: string, city: string, postal_code: ?string}>
     */
    private function lockers(): array
    {
        return array_column((new BoxNowService())->getLockers(), null, 'id');
    }

    /**
     * @return list<string> the ids of the lockers the checkout lists for a city
     */
    private function lockersOf(string $city): array
    {
        $ids = array_map(fn ($point) => $point->code, $this->method()->points($city, DestinationType::Locker));
        sort($ids, SORT_NATURAL);

        return $ids;
    }

    public function test_every_row_of_the_list_is_a_locker_with_an_id_a_name_and_a_city(): void
    {
        $lockers = $this->lockers();

        $this->assertCount(18, $lockers);
        $this->assertSame(
            ['id' => '1840', 'name' => 'BOX NOW - New Avenue, ул. Проф. Храбър Попов 14, София - 24/7', 'city' => 'София', 'postal_code' => '1700'],
            $lockers['1840'],
        );

        foreach ($lockers as $locker) {
            $this->assertNotSame('', $locker['name']);
            $this->assertNotSame('', $locker['city']);
        }
    }

    public function test_the_cities_are_listed_once_each_the_ones_with_the_most_lockers_first(): void
    {
        $this->assertSame(
            ['София', 'Червен бряг', 'Варна', 'Първомай', 'с. Нови хан', 'Войводиново', 'Казанлък', 'с. Първомай', 'с. Войводиново'],
            array_map(fn ($place) => $place->name, $this->method()->places('')),
        );
    }

    public function test_a_city_written_with_a_latin_letter_or_a_space_after_it_is_the_same_city(): void
    {
        // "Варнa" ends in a Latin letter, "София " in a space.
        $this->assertSame(['2721', '4179'], $this->lockersOf('Варна'));
        $this->assertSame(['1840', '2832', '3165', '7665', '11661'], $this->lockersOf('София'));
    }

    public function test_a_city_written_in_two_cases_takes_the_spelling_most_of_its_lockers_have(): void
    {
        // "Червен бряг" twice and "Червен Бряг" once.
        $this->assertSame(['8400', '8404', '13385'], $this->lockersOf('Червен бряг'));
        $this->assertSame([], $this->lockersOf('Червен Бряг'));

        // "с. Нови хан" and "с. Нови Хан", once each: the first one.
        $this->assertSame(['4230', '5392'], $this->lockersOf('с. Нови хан'));
    }

    /**
     * Първомай is a town and с. Първомай a village in another part of the country.
     */
    public function test_a_town_and_a_village_of_the_same_name_stay_two_places(): void
    {
        $this->assertSame(['3100', '4295'], $this->lockersOf('Първомай'));
        $this->assertSame(['8342'], $this->lockersOf('с. Първомай'));

        // So the "с." is never dropped, and a village the list names both ways stays twice in it.
        $this->assertSame(['3297'], $this->lockersOf('Войводиново'));
        $this->assertSame(['13522'], $this->lockersOf('с. Войводиново'));
    }

    public function test_a_postal_code_that_is_not_a_number_is_not_kept(): void
    {
        $lockers = $this->lockers();

        // "-1000" in the list.
        $this->assertNull($lockers['2832']['postal_code']);
        $this->assertSame('1233', $lockers['3165']['postal_code']);
        $this->assertNull($this->method()->destination(DestinationType::Locker, ['country_code' => 'BG', 'point_code' => '2832'])->postalCode);
    }

    public function test_the_name_of_a_locker_has_no_space_around_it(): void
    {
        // "… - 24/7 " in the list.
        $this->assertSame('BOX NOW - ул. Войнишка 51, Казанлък - 24/7', $this->lockers()['3760']['name']);
    }

    /**
     * The list has two states, "boxnow-ready" and "only-delivery". By its name the second one
     * takes deliveries too; BoxNow has not confirmed that reading.
     */
    public function test_a_locker_that_only_takes_deliveries_can_be_chosen(): void
    {
        $this->assertContains('3165', $this->lockersOf('София'));
        $this->assertSame('София', $this->method()->destination(DestinationType::Locker, ['country_code' => 'BG', 'point_code' => '3165'])->city);
    }
}
