<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

use Shared\Domain\Clock;

/**
 * A day as the blog counts it: in Madrid, whatever time zone the server keeps.
 */
final readonly class Day
{
    public const string TIME_ZONE = 'Europe/Madrid';

    private function __construct(private string $value) {}

    public static function today(): self
    {
        return new self(Clock::now()->setTimezone(new \DateTimeZone(self::TIME_ZONE))->format('Y-m-d'));
    }

    /**
     * The day a date stored by the database names, read as is.
     */
    public static function fromDate(\DateTimeImmutable $date): self
    {
        return new self($date->format('Y-m-d'));
    }

    /**
     * The date to store, at midnight of no time zone in particular.
     */
    public function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->value);
    }

    /**
     * When the day is over in Madrid.
     */
    public function endsAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->value, new \DateTimeZone(self::TIME_ZONE))->modify('+1 day');
    }

    public function value(): string
    {
        return $this->value;
    }
}
