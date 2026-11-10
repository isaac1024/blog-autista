<?php

declare(strict_types=1);

namespace Cms\Analytics\Domain;

use Shared\Domain\Clock;

/**
 * A run of days, first and last included, as the site counts them: in
 * Madrid, whatever time zone the server keeps.
 */
final readonly class Days
{
    public const string TIME_ZONE = 'Europe/Madrid';

    /**
     * The hour of Madrid by which the nightly count is done: it runs between
     * 00:10 and 02:10, as summer time moves it.
     */
    public const int NIGHTLY_COUNT_DONE_BY = 3;

    private function __construct(
        private \DateTimeImmutable $first,
        private \DateTimeImmutable $last,
    ) {}

    public static function today(): self
    {
        $today = self::todayInMadrid();

        return new self($today, $today);
    }

    /**
     * The days of the period, ending yesterday: today isn't over yet.
     */
    public static function endingYesterday(Period $period): self
    {
        $today = self::todayInMadrid();

        return new self($today->modify(\sprintf('-%d days', $period->days())), $today->modify('-1 day'));
    }

    /**
     * Whether a day still kept uncounted should have been counted by now: any
     * before yesterday, and yesterday once the night's count is done.
     */
    public static function isOverdue(string $day): bool
    {
        $now = Clock::now()->setTimezone(new \DateTimeZone(self::TIME_ZONE));
        $due = self::todayInMadrid()->modify($now >= $now->setTime(self::NIGHTLY_COUNT_DONE_BY, 0) ? '-1 day' : '-2 days');

        return $day <= $due->format('Y-m-d');
    }

    public function first(): string
    {
        return $this->first->format('Y-m-d');
    }

    public function last(): string
    {
        return $this->last->format('Y-m-d');
    }

    /**
     * Every day of the run, oldest first.
     *
     * @return list<string>
     */
    public function each(): array
    {
        $days = [];

        for ($day = $this->first; $day <= $this->last; $day = $day->modify('+1 day')) {
            $days[] = $day->format('Y-m-d');
        }

        return $days;
    }

    private static function todayInMadrid(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(Clock::now()->setTimezone(new \DateTimeZone(self::TIME_ZONE))->format('Y-m-d'));
    }
}
