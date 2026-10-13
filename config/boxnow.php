<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | BoxNow
    |--------------------------------------------------------------------------
    |
    | The partner API of BoxNow and the account it is called with. It is asked
    | only to create a delivery; the checkout needs none of these four.
    |
    */
    'base_url'      => env('BOXNOW_BASE_URL'),
    'auth_url'      => env('BOXNOW_AUTH_URL'),
    'client_id'     => env('BOXNOW_CLIENT_ID'),
    'client_secret' => env('BOXNOW_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Lockers
    |--------------------------------------------------------------------------
    |
    | The list of the lockers the customer picks from: a public file, read
    | without credentials and cached for a day. `country` is the country of
    | that list (ISO 3166-1 alpha-2): BoxNow publishes one file per country.
    |
    */
    'locations_url' => env('BOXNOW_LOCATIONS_URL', 'https://locationapi-production.boxnow.bg/v1/apms_bg-BG.json'),
    'country'       => env('BOXNOW_COUNTRY', 'BG'),

    /*
    |--------------------------------------------------------------------------
    | Prices
    |--------------------------------------------------------------------------
    |
    | The price of a delivery to a locker for every currency BoxNow is offered
    | in. Nothing is converted: a parcel in a currency that is not listed is
    | not offered BoxNow. BOXNOW_FLAT_PRICE is the price in BOXNOW_CURRENCY,
    | which is APP_DEFAULT_CURRENCY when empty; publish this file to add more.
    |
    */
    'prices' => [
        env('BOXNOW_CURRENCY') ?: env('APP_DEFAULT_CURRENCY', 'EUR') => env('BOXNOW_FLAT_PRICE', '4.99'),
    ],
];
