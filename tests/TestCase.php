<?php

namespace Dabergut\Idempotency\Tests;

use Dabergut\Idempotency\IdempotencyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            IdempotencyServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        $router->post('/test-endpoint', function () {
            return response()->json([
                'id' => rand(1, 999999),
                'created' => true,
            ], 201);
        })->middleware('idempotent');

        $router->get('/test-get', function () {
            return response()->json(['ok' => true]);
        })->middleware('idempotent');
    }
}
