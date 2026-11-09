<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Outbound port that keeps two changes over the same things from running at
 * once, so a rule checked before saving still holds when it is saved.
 */
interface MutualExclusion
{
    /**
     * Runs the work holding every key, waiting while another change holds
     * any of them, and lets them go once it ends, however it ends.
     *
     * The keys are taken in the same order whatever order they come in, so
     * two changes over the same keys never wait on each other for good.
     *
     * @template T
     *
     * @param list<string>  $keys names of what the work changes or relies on, such as "page:<id>"
     * @param \Closure(): T $work
     *
     * @return T
     */
    public function run(array $keys, \Closure $work): mixed;
}
