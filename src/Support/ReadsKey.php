<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

/**
 * Read one entry from a payload. An exact key wins, so names that contain dots
 * stay intact; otherwise the key is a nested path such as cart.tip.
 */
trait ReadsKey
{
    protected static function read(mixed $payload, ?string $key, mixed $default): mixed
    {
        if ($key === null) {
            return $payload;
        }

        if (is_array($payload) && array_key_exists($key, $payload)) {
            return $payload[$key] ?? $default;
        }

        return data_get($payload, $key, $default);
    }
}
