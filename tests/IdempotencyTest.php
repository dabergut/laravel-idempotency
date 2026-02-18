<?php

use Illuminate\Support\Facades\Cache;

it('processes a request normally without an idempotency key', function () {
    $response = $this->postJson('/test-endpoint', ['name' => 'test']);

    $response->assertStatus(201);
    expect($response->headers->has('Idempotent-Replayed'))->toBeFalse();
});

it('returns the same response for repeated requests with the same key', function () {
    $key = fake()->uuid();

    $first = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => $key,
    ]);

    $second = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => $key,
    ]);

    $first->assertStatus(201);
    $second->assertStatus(201);

    expect($first->json('id'))->toBe($second->json('id'));
    expect($first->headers->get('Idempotent-Replayed'))->toBe('false');
    expect($second->headers->get('Idempotent-Replayed'))->toBe('true');
});

it('treats different keys as separate requests', function () {
    $first = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => fake()->uuid(),
    ]);

    $second = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => fake()->uuid(),
    ]);

    expect($first->json('id'))->not->toBe($second->json('id'));
});

it('rejects keys that are too short', function () {
    $response = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => 'short',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['message' => 'Idempotency key must be at least 8 characters.']);
});

it('rejects reused keys with different request bodies', function () {
    $key = fake()->uuid();

    $this->postJson('/test-endpoint', ['name' => 'first'], [
        'Idempotency-Key' => $key,
    ])->assertStatus(201);

    $this->postJson('/test-endpoint', ['name' => 'second'], [
        'Idempotency-Key' => $key,
    ])->assertStatus(422)->assertJsonFragment([
        'message' => 'Idempotency key already used with a different request body.',
    ]);
});

it('ignores GET requests even with the middleware applied', function () {
    $key = fake()->uuid();

    $first = $this->getJson('/test-get', ['Idempotency-Key' => $key]);
    $second = $this->getJson('/test-get', ['Idempotency-Key' => $key]);

    $first->assertOk();
    $second->assertOk();
    expect($first->headers->has('Idempotent-Replayed'))->toBeFalse();
});

it('replays the original status code', function () {
    $key = fake()->uuid();

    $first = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => $key,
    ]);

    $second = $this->postJson('/test-endpoint', ['name' => 'test'], [
        'Idempotency-Key' => $key,
    ]);

    expect($second->getStatusCode())->toBe(201);
});
