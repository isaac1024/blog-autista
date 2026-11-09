<?php

declare(strict_types=1);

namespace Web\Analytics\Application;

use Shared\Domain\MutualExclusion;
use Shared\Domain\Transaction;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\VisitRepository;

/**
 * Counts the visits of every day already over and forgets them, so what is
 * kept of a day tells no reader from another. A day is counted at once or
 * not at all, and one left uncounted is counted the next time.
 */
final readonly class VisitCounter
{
    private const string LOCK = 'web-visit-count';

    public function __invoke(VisitRepository $visitRepository, MutualExclusion $mutualExclusion, Transaction $transaction): void
    {
        $mutualExclusion->run([self::LOCK], static function () use ($visitRepository, $transaction): void {
            foreach ($visitRepository->daysToCountBefore(Day::today()) as $day) {
                $transaction->run(static fn () => $visitRepository->count($day));
            }
        });
    }
}
