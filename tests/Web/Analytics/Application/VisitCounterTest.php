<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Transaction;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Web\Analytics\Application\VisitCounter;
use Web\Analytics\Domain\VisitRepository;

final class VisitCounterTest extends TestCase
{
    /**
     * Two counts at once would count a day twice, so one waits for the other.
     */
    public function testItCountsUnderALockOfItsOwn(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $visitRepository = self::createStub(VisitRepository::class);
        $visitRepository->method('daysToCountBefore')->willReturn([]);

        new VisitCounter()($visitRepository, $mutualExclusion, self::createStub(Transaction::class));

        self::assertSame(['web-visit-count'], $mutualExclusion->lastHeld());
    }
}
