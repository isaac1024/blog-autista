<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Application;

use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Analytics\Application\VisitCounter;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\VisitRepository;

/**
 * Every day already over that still has visits is counted, each at once or
 * not at all, one count at a time.
 */
final class VisitCounterTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * Today, in Madrid, isn't over: only the days before it are counted,
     * oldest first, each in a transaction of its own.
     */
    public function testItCountsEveryDayBeforeTodayEachInATransactionOfItsOwn(): void
    {
        $transaction = new RecordingTransaction();
        $counted = [];
        $repository = $this->createMock(VisitRepository::class);
        $repository->expects(self::once())
            ->method('daysToCountBefore')
            ->with(self::equalTo(Day::today()))
            ->willReturn([Day::fromDate(new \DateTimeImmutable('2026-11-05')), Day::fromDate(new \DateTimeImmutable('2026-11-06'))]);
        $repository->expects(self::exactly(2))->method('count')->willReturnCallback(static function (Day $day) use ($transaction, &$counted): void {
            $counted[] = [$day->value(), $transaction->isRunning()];
        });

        new VisitCounter()($repository, new RecordingMutualExclusion(), $transaction);

        self::assertSame([['2026-11-05', true], ['2026-11-06', true]], $counted);
    }

    /**
     * Two counts at once would count a day twice, so one waits for the other.
     */
    public function testItCountsUnderALockOfItsOwn(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $held = null;
        $repository = $this->createMock(VisitRepository::class);
        $repository->expects(self::once())->method('daysToCountBefore')->willReturnCallback(static function () use ($mutualExclusion, &$held): array {
            $held = $mutualExclusion->isHolding();

            return [];
        });
        $repository->expects(self::never())->method('count');

        new VisitCounter()($repository, $mutualExclusion, new RecordingTransaction());

        self::assertSame([['web-visit-count'], true], [$mutualExclusion->lastHeld(), $held]);
    }
}
