<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Header Name
    |--------------------------------------------------------------------------
    |
    | The HTTP header your clients will use to pass the idempotency key.
    | Stripe uses "Idempotency-Key", most of the internet follows suit.
    | Change it if you have a reason to, but you probably don't.
    |
    */

    'header' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),

    /*
    |--------------------------------------------------------------------------
    | Key Lifetime (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a stored response sticks around. Stripe uses 24 hours.
    | After this, the same key will be treated as a fresh request.
    |
    */

    'ttl' => env('IDEMPOTENCY_TTL', 1440),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Which cache store to use for persisting responses. Set to null
    | to use your default cache driver. Redis is recommended for
    | production — file cache works but won't survive deploys.
    |
    */

    'store' => env('IDEMPOTENCY_STORE', null),

    /*
    |--------------------------------------------------------------------------
    | Enforced Methods
    |--------------------------------------------------------------------------
    |
    | HTTP methods that require idempotency keys. GET, PUT and DELETE
    | are inherently idempotent by spec, so we only care about POST
    | and PATCH by default. Add or remove as needed.
    |
    */

    'methods' => ['POST', 'PATCH'],

    /*
    |--------------------------------------------------------------------------
    | Key Validation
    |--------------------------------------------------------------------------
    |
    | Enforce a minimum length for keys to prevent lazy "1" or "test"
    | keys in production. Set to 0 to disable. UUIDs are 36 chars.
    |
    */

    'min_key_length' => 8,

    /*
    |--------------------------------------------------------------------------
    | Fingerprint Request Body
    |--------------------------------------------------------------------------
    |
    | When true, the middleware will hash the request body along with
    | the idempotency key. If someone reuses a key with a different
    | payload, they'll get a 422 instead of a stale response.
    | This is what Stripe does. You should probably leave it on.
    |
    */

    'enforce_body_match' => true,

];
