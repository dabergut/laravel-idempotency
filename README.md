# Laravel Idempotency

[![Tests](https://github.com/dabergut/-laravel-idempotency/actions/workflows/tests.yml/badge.svg)](https://github.com/dabergut/-laravel-idempotency/actions)

Drop-in middleware that prevents duplicate POST/PATCH processing in your Laravel API.

Client sends an `Idempotency-Key` header, server stores the response. Same key comes in again — cached response goes back, controller never fires twice. No double charges, no duplicate orders, no angry customers.

## Why

Every API that mutates state has the same problem: the client sends a request, something hiccups (timeout, flaky connection, eager retry logic), and the same request arrives twice. Without idempotency handling your API happily processes it again.

You can write this yourself. I've done it about four times across different projects before extracting it into this package. The tricky bits are locking (concurrent duplicate requests), body fingerprinting (same key, different payload), and scoping keys per user.

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require dabergut/laravel-idempotency
```

The service provider registers automatically. Publish the config if you need to tweak anything:

```bash
php artisan vendor:publish --tag=idempotency-config
```

## Usage

Add the middleware to routes that shouldn't be processed twice:

```php
Route::post('/orders', CreateOrderController::class)
    ->middleware('idempotent');
```

Or apply it to a group:

```php
Route::middleware('idempotent')->group(function () {
    Route::post('/orders', CreateOrderController::class);
    Route::post('/payments', ProcessPaymentController::class);
    Route::patch('/orders/{order}', UpdateOrderController::class);
});
```

That's it. Your clients need to send an `Idempotency-Key` header with their requests:

```
POST /api/orders HTTP/1.1
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
Content-Type: application/json

{"product_id": 42, "quantity": 1}
```

First request processes normally. Second request with the same key returns the stored response without hitting your controller.

## Response headers

Every response from an idempotent endpoint includes:

| Header | Value | Meaning |
|---|---|---|
| `Idempotent-Replayed` | `false` | Fresh response, controller executed |
| `Idempotent-Replayed` | `true` | Cached response, controller skipped |

## What happens when

| Scenario | Result |
|---|---|
| No `Idempotency-Key` header | Request processed normally, no caching |
| Key present, first time seen | Request processed, response cached |
| Key present, seen before, same body | Cached response returned (201, not 200) |
| Key present, seen before, different body | 422 error — key reuse with different payload |
| Key too short (< 8 chars by default) | 422 error |
| Concurrent duplicate requests | Second request waits for lock, then returns cached response |
| GET or DELETE request | Middleware does nothing (idempotent by HTTP spec) |

## Configuration

```php
// config/idempotency.php

return [
    // Header name. Stripe uses the same one.
    'header' => 'Idempotency-Key',

    // How long to keep stored responses (minutes). Default: 24 hours.
    'ttl' => 1440,

    // Cache store. null = your default driver. Redis recommended.
    'store' => null,

    // Which HTTP methods to enforce on.
    'methods' => ['POST', 'PATCH'],

    // Minimum key length. UUIDs are 36 chars.
    'min_key_length' => 8,

    // Reject reused keys with different request bodies.
    'enforce_body_match' => true,
];
```

## User scoping

Keys are automatically scoped to the authenticated user. User A and User B can both send `Idempotency-Key: abc` without collision. Unauthenticated requests share a global scope — keep that in mind if your public endpoints use this middleware.

## Cache backend

This works with any Laravel cache driver, but **use Redis in production**. File cache works for development but won't survive deployments and doesn't support atomic locks properly.

```env
IDEMPOTENCY_STORE=redis
```

## Testing

```bash
composer test
```

## License

MIT
