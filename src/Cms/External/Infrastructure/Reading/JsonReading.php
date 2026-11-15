<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteUnreachable;

/**
 * Reads the JSON object an endpoint of a provider answers, and the texts in
 * it, for the readings of each provider.
 */
final readonly class JsonReading
{
    private const int MAX_BYTES = 1_000_000;

    public function __construct(private RemoteHttp $http) {}

    /**
     * The object the address answers, or null when it answers none, or an
     * error.
     *
     * @return ?array<array-key, mixed>
     */
    public function object(string $url): ?array
    {
        try {
            $response = $this->http->get($url, self::MAX_BYTES, 'application/json');
        } catch (RemoteUnreachable) {
            return null;
        }

        return $response->isSuccessful() ? $response->json() : null;
    }

    /**
     * The text under the key, trimmed, or null when it holds none.
     *
     * @param array<array-key, mixed> $data
     */
    public static function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
