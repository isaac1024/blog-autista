<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many searches a reader may ask the site in a while, told by the
 * address they come from. The limiter keeps no address: it is keyed by a
 * hash of it with the secret of the app.
 */
final readonly class SearchRateLimit
{
    public function __construct(
        #[Target('web_search')]
        private RateLimiterFactoryInterface $limiter,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {}

    /**
     * Whether the reader at the address may search once more now, which
     * counts against their limit.
     */
    public function accepts(string $address): bool
    {
        return $this->limiter->create(hash_hmac('sha256', $address, $this->secret))->consume()->isAccepted();
    }
}
