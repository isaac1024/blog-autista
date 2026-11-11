<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\Day;

/**
 * How many visits a reader may send in a while, told by the address they
 * come from. The limiter keeps no address: it is keyed by a hash of it with
 * the salt of the day.
 */
final readonly class VisitRateLimit
{
    public function __construct(
        #[Target('web_visit')]
        private RateLimiterFactoryInterface $limiter,
        private DailySalt $dailySalt,
    ) {}

    /**
     * Whether the reader at the address may send one more visit now, which
     * counts against their limit.
     */
    public function accepts(string $address): bool
    {
        return $this->limiter->create(hash_hmac('sha256', $address, $this->dailySalt->forDay(Day::today())))->consume()->isAccepted();
    }
}
