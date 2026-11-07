<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Clock;

use Shared\Domain\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The time of the Symfony clock, which tests can freeze.
 */
#[AsAlias(Clock::class)]
final readonly class SymfonyClock implements Clock
{
    public function __construct(private ClockInterface $clock) {}

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }
}
