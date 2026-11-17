<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Scheduler;

use Shared\Domain\MutualExclusion;
use Shared\Domain\SiteTimeZone;
use Shared\Domain\Transaction;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;
use Web\Analytics\Application\VisitCounter;
use Web\Analytics\Domain\VisitRepository;

/**
 * Counts the visits of the days already over every night. A day lasts 86400
 * seconds here, so the time moves an hour with summer time, between 00:10
 * and 02:10 in Madrid: always once the day is over and its salt is gone.
 */
#[AsPeriodicTask(frequency: '1 day', from: '01:10 '.SiteTimeZone::NAME)]
final readonly class NightlyVisitCount
{
    public function __construct(
        private VisitRepository $visitRepository,
        private MutualExclusion $mutualExclusion,
        private Transaction $transaction,
    ) {}

    public function __invoke(): void
    {
        new VisitCounter()($this->visitRepository, $this->mutualExclusion, $this->transaction);
    }
}
