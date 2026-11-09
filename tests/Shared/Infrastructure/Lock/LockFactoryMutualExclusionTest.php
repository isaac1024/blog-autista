<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Lock;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Lock\LockFactoryMutualExclusion;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The work runs holding every key, each once and in the same order whatever
 * order they come in, so two changes over the same keys never wait on each
 * other for good; and lets them go however it ends.
 */
final class LockFactoryMutualExclusionTest extends TestCase
{
    public function testItHoldsEveryKeyOnceInOrderWhileTheWorkRuns(): void
    {
        $store = new RecordingStore();
        $held = null;

        $result = new LockFactoryMutualExclusion(new LockFactory($store))->run(['page:b', 'address:/x.html', 'page:b', 'image:a'], static function () use ($store, &$held): string {
            $held = $store->held();

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(['address:/x.html', 'image:a', 'page:b'], $store->taken);
        self::assertSame(['address:/x.html', 'image:a', 'page:b'], $held);
        self::assertSame([], $store->held());
    }

    public function testItLetsTheKeysGoWhenTheWorkFails(): void
    {
        $store = new RecordingStore();

        try {
            new LockFactoryMutualExclusion(new LockFactory($store))->run(['page:b', 'image:a'], static fn () => throw new \RuntimeException('Refused.'));
            self::fail('The work did not fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Refused.', $exception->getMessage());
        }

        self::assertSame(['image:a', 'page:b'], $store->taken);
        self::assertSame([], $store->held());
    }
}

/**
 * A store that tells which keys were taken, in order, and which are held.
 *
 * @internal
 */
final class RecordingStore implements PersistingStoreInterface
{
    /**
     * @var list<string>
     */
    public array $taken = [];

    private InMemoryStore $store;

    /**
     * @var array<string, true>
     */
    private array $held = [];

    public function __construct()
    {
        $this->store = new InMemoryStore();
    }

    #[\Override]
    public function save(Key $key): void
    {
        $this->store->save($key);
        $this->taken[] = (string) $key;
        $this->held[(string) $key] = true;
    }

    #[\Override]
    public function delete(Key $key): void
    {
        $this->store->delete($key);
        unset($this->held[(string) $key]);
    }

    #[\Override]
    public function exists(Key $key): bool
    {
        return $this->store->exists($key);
    }

    #[\Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->store->putOffExpiration($key, $ttl);
    }

    /**
     * @return list<string>
     */
    public function held(): array
    {
        return array_keys($this->held);
    }
}
