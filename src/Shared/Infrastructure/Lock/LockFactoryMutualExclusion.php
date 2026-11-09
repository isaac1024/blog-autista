<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Lock;

use Shared\Domain\MutualExclusion;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Outbound adapter that holds a lock of symfony/lock per key, in the store
 * the LOCK_DSN names. Wraps symfony/lock, so contexts never depend on it
 * directly.
 */
#[AsAlias(MutualExclusion::class)]
final readonly class LockFactoryMutualExclusion implements MutualExclusion
{
    public function __construct(private LockFactory $lockFactory) {}

    #[\Override]
    public function run(array $keys, \Closure $work): mixed
    {
        $keys = array_values(array_unique($keys));
        sort($keys);

        /** @var list<LockInterface> $locks */
        $locks = [];

        try {
            foreach ($keys as $key) {
                $lock = $this->lockFactory->createLock($key);
                $lock->acquire(true);
                $locks[] = $lock;
            }

            return $work();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }
}
