<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Salt;

use Shared\Domain\Clock;
use Shared\Domain\MutualExclusion;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\Day;

/**
 * Outbound adapter that keeps the salt of each day in its own cache pool, in
 * Redis, out of the database and its backups. It is made once, under a lock,
 * and expires a few minutes after its day, so a visit sent at midnight still
 * finds it.
 */
#[AsAlias(DailySalt::class)]
final readonly class CacheDailySalt implements DailySalt
{
    private const string MARGIN = '+5 minutes';

    public function __construct(
        #[Autowire(service: 'web.visit_salt.cache')]
        private CacheInterface $cache,
        private MutualExclusion $mutualExclusion,
    ) {}

    #[\Override]
    public function forDay(Day $day): string
    {
        $key = 'salt-'.$day->value();

        return $this->mutualExclusion->run(['web-visit-'.$key], fn (): string => $this->cache->get($key, static function (ItemInterface $item) use ($day): string {
            // A lifetime counted from the clock of the blog, the one days are told by.
            $item->expiresAfter(max(1, $day->endsAt()->modify(self::MARGIN)->getTimestamp() - Clock::now()->getTimestamp()));

            return bin2hex(random_bytes(32));
        }));
    }
}
