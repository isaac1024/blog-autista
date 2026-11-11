<?php

declare(strict_types=1);

namespace Shared\Tests\Domain;

use Shared\Domain\Transaction;

/**
 * Transaction that runs the work at once, telling whether a run is under
 * way, so a test knows a change saves all of it within one transaction.
 * Keeping all of it or none is the adapter's to prove.
 */
final class RecordingTransaction implements Transaction
{
    private bool $running = false;

    #[\Override]
    public function run(\Closure $work): mixed
    {
        $this->running = true;

        try {
            return $work();
        } finally {
            $this->running = false;
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }
}
