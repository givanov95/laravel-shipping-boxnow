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
                'name'        => self::name($row),
                'city'        => $city,
                'postal_code' => self::postalCode($row->postalCode ?? null),
            ];
        }

        $lockers = self::withOneSpellingOfEachCity($lockers);

        if ($lockers !== []) {
            Cache::put(self::LOCKERS_CACHE_KEY, $lockers, now()->addDay());
        }

        return $lockers;
    }

    /**
     * The list has cities typed with a Latin letter in place of the Cyrillic one it looks
     * like, with a space after the name, and a village with and without a space after its
     * "с.": each would be a city of its own.
     */
    private static function city(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if (preg_match('/\p{Cyrillic}/u', $name) !== 1) {
            return $name;
        }

        $name = strtr($name, [
            'a' => 'а', 'c' => 'с', 'e' => 'е', 'o' => 'о', 'p' => 'р', 'x' => 'х', 'y' => 'у',
            'A' => 'А', 'B' => 'В', 'C' => 'С', 'E' => 'Е', 'H' => 'Н', 'K' => 'К', 'M' => 'М',
            'O' => 'О', 'P' => 'Р', 'T' => 'Т', 'X' => 'Х',
        ]);

        return (string) preg_replace('/^с\.(?=\S)/u', 'с. ', $name);
    }

    /**
     * The list writes one city in more than one case ("Червен бряг", "Червен Бряг"). The
     * spelling most of its lockers have stands for all of them; the first one, when they tie.
     *
     * The "с." of a village is kept as it is written: a town and a village share a name
     * ("Първомай", "с. Първомай"), so a name with it and one without are not the same place.
     *
     * @param  list<array{id: string, name: string, city: string, postal_code: ?string}>  $lockers
     * @return list<array{id: string, name: string, city: string, postal_code: ?string}>
     */
    private static function withOneSpellingOfEachCity(array $lockers): array
    {
        $spellings = [];

        foreach ($lockers as $locker) {
            $key = mb_strtolower($locker['city']);

            $spellings[$key][$locker['city']] = ($spellings[$key][$locker['city']] ?? 0) + 1;
        }

        foreach ($lockers as $index => $locker) {
            $counts = $spellings[mb_strtolower($locker['city'])];
            arsort($counts);

            $lockers[$index]['city'] = (string) array_key_first($counts);
        }

        return $lockers;
    }

    /**
     * The name the list gives the locker, else its title, else its id.
     */
    private static function name(object $row): string
    {
        foreach ([$row->name ?? null, $row->title ?? null] as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                return $name;
            }
        }

        return (string) $row->id;
    }

    /**
     * The list has lockers with "-1000" for a postal code: what is not a number is not kept.
     */
    private static function postalCode(mixed $code): ?string
    {
        $code = trim((string) $code);

        return ctype_digit($code) ? $code : null;
    }
}
