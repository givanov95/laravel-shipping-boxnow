<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Closure;
use Givanov95\Shipping\Contracts\DeliveryCountries;
use Givanov95\Shipping\Contracts\DeliveryMethod;
use Givanov95\Shipping\Contracts\Parcel;
use Givanov95\Shipping\Contracts\PlaceDirectory;
use Givanov95\Shipping\Destination;
use Givanov95\Shipping\DestinationType;
use Givanov95\Shipping\Place;
use Givanov95\Shipping\Point;

/**
 * BoxNow behind the contract: to one of its lockers, picked by city from the list it publishes.
 */
class BoxNowMethod implements DeliveryMethod, PlaceDirectory, DeliveryCountries
{
    private const SEARCH_LIMIT = 20;

    public function __construct(private readonly BoxNowService $boxNow)
    {
    }

    public function key(): string
    {
        return 'box_now';
    }

    public function destinationTypes(): array
    {
        return [DestinationType::Locker];
    }

    /**
     * Only the locker is asked for: its name, city and postal code are read from the list.
     */
    public function rules(DestinationType $type): array
    {
        return [
            'point_code' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->locker((string) $value) === null) {
                        $fail(__('The selected locker is not available.'));
                    }
                },
            ],
        ];
    }

    public function destination(DestinationType $type, array $choice): Destination
    {
        $locker = $this->locker((string) $choice['point_code']);

        return new Destination(
            method: $this->key(),
            type: $type,
            countryCode: $choice['country_code'],
            city: $locker['city'] ?? null,
            postalCode: $locker['postal_code'] ?? null,
            pointCode: (string) $choice['point_code'],
            pointName: $locker['name'] ?? null,
        );
    }

    /**
     * One price for every locker, in the currency of the parcel. Null for a parcel in a currency
     * BoxNow has no price in: the shop has no exchange rate.
     */
    public function price(Destination $destination, Parcel $parcel): ?Money
    {
        $price = self::priceIn($parcel->getCurrency());

        return $price === null ? null : Money::of($price, $parcel->getCurrency(), roundingMode: RoundingMode::HalfUp);
    }

    /**
     * The lockers are in the country of the list; a parcel in a currency without a price is not offered BoxNow.
     */
    public function countries(string $currency): array
    {
        return self::priceIn($currency) === null ? [] : [mb_strtoupper((string) config('boxnow.country'))];
    }

    public function freeDeliveryApplies(Destination $destination): bool
    {
        return true;
    }

    /**
     * The cities that have a locker, the ones with the most lockers first.
     */
    public function places(string $search): array
    {
        $search = trim($search);
        $cities = array_count_values(array_column($this->boxNow->getLockers(), 'city'));
        arsort($cities);

        $places = [];

        foreach (array_keys($cities) as $city) {
            $city = (string) $city;

            if ($search !== '' && mb_stripos($city, $search) === false) {
                continue;
            }

            // BoxNow has no id for a city: the lockers carry its name.
            $places[] = new Place($city, $city);

            if (count($places) === self::SEARCH_LIMIT) {
                break;
            }
        }

        return $places;
    }

    public function points(string $placeId, DestinationType $type): array
    {
        if ($type !== DestinationType::Locker) {
            return [];
        }

        $points = [];

        foreach ($this->boxNow->getLockers() as $locker) {
            if ($locker['city'] === $placeId) {
                $points[] = new Point($locker['id'], $locker['name']);
            }
        }

        usort($points, fn (Point $a, Point $b) => strnatcasecmp($a->name, $b->name));

        return $points;
    }

    /**
     * A locker is not an address: there are no streets to choose from.
     */
    public function streets(string $placeId, string $search): array
    {
        return [];
    }

    private static function priceIn(string $currency): ?string
    {
        $price = config('boxnow.prices')[$currency] ?? null;

        return is_numeric($price) ? (string) $price : null;
    }

    /**
     * @return array{id: string, name: string, city: string, postal_code: ?string}|null
     */
    private function locker(string $id): ?array
    {
        foreach ($this->boxNow->getLockers() as $locker) {
            if ($locker['id'] === $id) {
                return $locker;
            }
        }

        return null;
    }
}
