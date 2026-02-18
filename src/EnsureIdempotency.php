<?php

namespace Dabergut\Idempotency;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldProcess($request)) {
            return $next($request);
        }

        $header = config('idempotency.header');
        $key = $request->header($header);

        if ($key === null) {
            return $next($request);
        }

        $minLength = config('idempotency.min_key_length', 8);

        if ($minLength > 0 && strlen($key) < $minLength) {
            return response()->json([
                'message' => "Idempotency key must be at least {$minLength} characters.",
            ], 422);
        }

        $cacheKey = $this->buildCacheKey($request, $key);
        $store = Cache::store(config('idempotency.store'));

        // Check if we already have a stored response for this key.
        $cached = $store->get($cacheKey);

        if ($cached !== null) {
            return $this->replayResponse($request, $cached);
        }

        // Acquire a lock to prevent duplicate processing of concurrent
        // requests with the same idempotency key. The loser waits up
        // to 10 seconds, then gets a 409.
        $lock = $store->lock("idempotency_lock:{$cacheKey}", 30);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A request with this idempotency key is already being processed.',
            ], 409);
        }

        try {
            // Double-check after acquiring lock — another process may
            // have finished while we were waiting.
            $cached = $store->get($cacheKey);

            if ($cached !== null) {
                return $this->replayResponse($request, $cached);
            }

            $response = $next($request);

            $this->storeResponse($store, $cacheKey, $request, $response);

            $response->headers->set('Idempotent-Replayed', 'false');

            return $response;
        } finally {
            $lock->release();
        }
    }

    protected function shouldProcess(Request $request): bool
    {
        $methods = array_map('strtoupper', config('idempotency.methods', ['POST', 'PATCH']));

        return in_array(strtoupper($request->method()), $methods, true);
    }

    protected function buildCacheKey(Request $request, string $idempotencyKey): string
    {
        $parts = ['idempotency', $idempotencyKey];

        // Scope to the authenticated user if available, so different
        // users can't collide on the same key.
        if ($request->user()) {
            $parts[] = 'user_' . $request->user()->getAuthIdentifier();
        }

        return implode(':', $parts);
    }

    protected function replayResponse(Request $request, array $cached): Response
    {
        if (config('idempotency.enforce_body_match', true)) {
            $currentFingerprint = $this->fingerprintBody($request);

            if ($cached['fingerprint'] !== $currentFingerprint) {
                return response()->json([
                    'message' => 'Idempotency key already used with a different request body.',
                ], 422);
            }
        }

        $response = response($cached['body'], $cached['status'])
            ->withHeaders($cached['headers']);

        $response->headers->set('Idempotent-Replayed', 'true');

        return $response;
    }

    protected function storeResponse($store, string $cacheKey, Request $request, Response $response): void
    {
        $ttl = config('idempotency.ttl', 1440);

        $store->put($cacheKey, [
            'status' => $response->getStatusCode(),
            'headers' => $this->serializableHeaders($response),
            'body' => $response->getContent(),
            'fingerprint' => $this->fingerprintBody($request),
        ], $ttl * 60);
    }

    protected function fingerprintBody(Request $request): string
    {
        return hash('xxh128', $request->getContent());
    }

    protected function serializableHeaders(Response $response): array
    {
        $skip = ['set-cookie', 'date', 'transfer-encoding'];

        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            if (in_array(strtolower($name), $skip, true)) {
                continue;
            }
            $headers[$name] = $values;
        }

        return $headers;
    }
}
