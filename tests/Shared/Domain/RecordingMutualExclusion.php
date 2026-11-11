<?php

declare(strict_types=1);

namespace Shared\Tests\Domain;

use Shared\Domain\MutualExclusion;

/**
 * Mutual exclusion that runs the work at once, telling which keys each run
 * held, so a test knows what a change holds while it checks and saves,
 * and whether it reads and saves while it holds them.
 */
final class RecordingMutualExclusion implements MutualExclusion
{
    /**
     * @var list<list<string>>
     */
    private array $held = [];

    private bool $holding = false;

    #[\Override]
    public function run(array $keys, \Closure $work): mixed
    {
        $keys = array_values(array_unique($keys));
        sort($keys);
        $this->held[] = $keys;
        $this->holding = true;

        try {
            return $work();
        } finally {
            $this->holding = false;
        }
    }

    /**
     * Whether a run is holding its keys now.
     */
    public function isHolding(): bool
    {
        return $this->holding;
    }

    /**
     * The keys of the last run, sorted, each once.
     *
     * @return list<string>
     */
    public function lastHeld(): array
    {
        return $this->held[array_key_last($this->held) ?? 0] ?? [];
    }
}
