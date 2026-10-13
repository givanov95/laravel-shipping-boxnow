<?php

declare(strict_types=1);

namespace Givanov95\Shipping\BoxNow;

use Givanov95\Shipping\BoxNow\Requests\GetLockersRequest;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BoxNowService
{
    private const LOCKERS_CACHE_KEY = 'boxnow-lockers';

    /**
     * The lockers of BoxNow, with what the checkout shows of each. The cart form lists them,
     * so a failing BoxNow gives an empty list. Only a real answer is cached: a failed request
     * must not hide the lockers for a day.
     *
     * @return list<array{id: string, name: string, city: string, postal_code: ?string}>
     */
    public function getLockers(): array
    {
        $cached = Cache::get(self::LOCKERS_CACHE_KEY);

        if (! empty($cached)) {
            return $cached;
        }

        try {
            $rows = (new BoxNow())->getLockers(new GetLockersRequest())->data ?? [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $lockers = [];

        foreach ($rows as $row) {
            $city = self::city((string) ($row->addressLine2 ?? ''));

            // A locker with no id cannot be ordered to, one with no city cannot be found.
            if (! isset($row->id) || $city === '') {
                continue;
            }

            $lockers[] = [
                'id'          => (string) $row->id,
                'name'        => (string) ($row->name ?? $row->title ?? $row->id),
                'city'        => $city,
                'postal_code' => isset($row->postalCode) ? (string) $row->postalCode : null,
            ];
        }

        if ($lockers !== []) {
            Cache::put(self::LOCKERS_CACHE_KEY, $lockers, now()->addDay());
        }

        return $lockers;
    }

    /**
     * The list has cities typed with a Latin letter in place of the Cyrillic one it looks
     * like, and with a space after the name: each would be a city of its own.
     */
    private static function city(string $name): string
    {
        $name = trim($name);

        if (preg_match('/\p{Cyrillic}/u', $name) !== 1) {
            return $name;
        }

        return strtr($name, [
            'a' => 'а', 'c' => 'с', 'e' => 'е', 'o' => 'о', 'p' => 'р', 'x' => 'х', 'y' => 'у',
            'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'K' => 'К', 'M' => 'М',
            'O' => 'О', 'P' => 'Р', 'T' => 'Т', 'X' => 'Х',
        ]);
    }
}
