# Laravel Shipping — BoxNow

BoxNow lockers as a delivery method of a Laravel shop: the client of the BoxNow API (on [`givanov95/typed-http`](https://github.com/givanov95/typed-http)) and the adapter that puts it behind the delivery contract of [`givanov95/laravel-shipping`](https://github.com/givanov95/laravel-shipping). The shop needs no code for BoxNow: the package registers the method, the shop offers it.

```bash
composer require givanov95/laravel-shipping-boxnow
```

> **0.x, the partner API is not yet checked.** The list of the lockers — all the checkout needs — is read from the real file. The partner API (the access token, `createDelivery`) is covered with `Http::fake()` only: run the checks under [Before going live](#before-going-live) with a stage account first.

## What the shop gets

The service provider is auto-discovered. It registers the method under the key `box_now` with the label `BoxNow`:

| | |
| --- | --- |
| Delivers to | a locker (`locker`), in the country of the list (`DeliveryCountries`) |
| Checkout lists | `PlaceDirectory`: the cities that have a locker (the ones with the most first) and the lockers of a city, from the list BoxNow publishes — a public file, read **without credentials** and cached for a day |
| Price | one price for every locker, per currency. A parcel in a currency without a price is not offered BoxNow: nothing is converted |
| Kept on the order | the `Destination`: the id of the locker, with its name, city and postal code read from the list, not from the form. A locker that is not in the list is refused |

Registered is not offered: which methods a customer can pick, and in what order, is the decision of the shop. It finds this one in the registry by its key, the key an order keeps: `app(Givanov95\Shipping\DeliveryMethods::class)->get('box_now')`.

The free delivery threshold of the shop holds for a locker.

## Configuration

```dotenv
BOXNOW_LOCATIONS_URL="https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json"
BOXNOW_COUNTRY=BG
BOXNOW_FLAT_PRICE=4.99
BOXNOW_CURRENCY=

BOXNOW_BASE_URL="https://api-stage.boxnow.bg/api/v1"
BOXNOW_AUTH_URL="https://api-stage.boxnow.bg/api/v1/auth-sessions"
BOXNOW_CLIENT_ID=
BOXNOW_CLIENT_SECRET=
```

| Key | What it is |
| --- | --- |
| `BOXNOW_LOCATIONS_URL` | The list of the lockers (stage: `https://locationapi-stage.boxnow.bg/v1/apms_bg-BG.json`) |
| `BOXNOW_COUNTRY` | The country of that list, ISO 3166-1 alpha-2. BoxNow publishes one file per country |
| `BOXNOW_FLAT_PRICE`, `BOXNOW_CURRENCY` | The price of a delivery and its currency; an empty currency is `APP_DEFAULT_CURRENCY` |
| `BOXNOW_BASE_URL`, `BOXNOW_AUTH_URL`, `BOXNOW_CLIENT_ID`, `BOXNOW_CLIENT_SECRET` | The partner API and its account. Only `createDelivery` needs them; the checkout works without |

The values are read through `config('boxnow.*')`. A second currency is a second entry in `boxnow.prices`, in the published file:

```bash
php artisan vendor:publish --tag=boxnow-config
```

The package brings its one customer-facing text ("The selected locker is not available.") in English and in Bulgarian (`bg`, `bg-BG`); a shop overrides it, or adds another language, with the same key in its own `lang/*.json`.

## Before going live

The list of the lockers is checked: on 2026-10-13 the code read the file BoxNow publishes, production and stage, and `tests/Fixtures/apms_bg-BG.json` keeps 18 of its rows as they were. What the list writes in a way of its own is evened out when it is read:

- a city with a Latin letter in place of the Cyrillic one it looks like, with a space after it, or in two cases ("Червен бряг", "Червен Бряг") is one city, under the spelling most of its lockers have;
- a postal code that is not a number ("-1000") is not kept, and a name has no space around it;
- a locker in the state `only-delivery` (16 of 964 that day) is listed with the `boxnow-ready` ones. By its name it takes deliveries and no parcels to send; BoxNow has not confirmed that reading;
- the "с." of a village is kept, because a town and a village share a name ("Първомай", "с. Първомай"). A village the list names with and without it ("Войводиново", "с. Войводиново") stays twice in the list of the cities.

With a stage account (`BOXNOW_CLIENT_ID`, `BOXNOW_CLIENT_SECRET`):

- `(new Givanov95\Shipping\BoxNow\BoxNow())->getAccessToken()` — a form POST to `auth-sessions` that answers with `access_token` and `expires_in`.
- `createDelivery` (`CreateDeliveryRequest`, Bearer token, JSON body) — nothing reads its answer yet and nothing calls it: record the structure it really has. Stage only.

Record what the API really answers as fixtures without personal data and tokens, and fix the code and the tests where they differ.

## Testing

```bash
composer test
```

## License

MIT
