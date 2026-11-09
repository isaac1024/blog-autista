<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Salt;

use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * A cache in memory that tells how long each value was given to live, in
 * seconds, which a real one keeps to itself.
 */
final class LifetimeRecordingCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @var array<string, int>
     */
    private array $lifetimes = [];

    /**
     * @param null|array<array-key, mixed> $metadata
     */
    #[\Override]
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (\array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        $item = new CacheItem();
        $save = true;
        $value = $callback($item, $save);
        $expiry = new \ReflectionProperty(CacheItem::class, 'expiry')->getValue($item);
        $this->lifetimes[$key] = is_numeric($expiry) ? (int) round((float) $expiry - microtime(true)) : 0;

        return $this->values[$key] = $value;
    }

    #[\Override]
    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->lifetimes[$key]);

        return true;
    }

    /**
     * @return array<string, int>
     */
    public function lifetimes(): array
    {
        return $this->lifetimes;
    }
}
