<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Tells the current time, so the domain records when things happen without
 * reading the system clock itself.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
