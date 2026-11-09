<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Warns, out of development and tests, when a visit comes from a private or
 * loopback address: readers come from the internet, so that address is a
 * proxy's, which means the proxies aren't trusted, and every reader would
 * count as one. The address itself is never logged.
 */
final readonly class ClientAddressWarning
{
    private const array QUIET_ENVIRONMENTS = ['dev', 'test'];

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    public function __invoke(?string $address): void
    {
        // The networks a proxy lives in, the same "private_ranges" trusts.
        if (\in_array($this->environment, self::QUIET_ENVIRONMENTS, true) || null === $address || !IpUtils::checkIp($address, IpUtils::PRIVATE_RANGES)) {
            return;
        }

        $this->logger->warning('A visit came from a private or loopback address: check SYMFONY_TRUSTED_PROXIES, or every reader counts as one.');
    }
}
