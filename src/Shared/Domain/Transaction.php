<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Outbound port that keeps a change made of several saves whole: everything
 * the work saves, and every event it tells, is kept together or not at all.
 */
interface Transaction
{
    /**
     * Runs the work and keeps what it saved once it returns; when it throws,
     * nothing it saved is kept, and the exception goes on.
     *
     * @template T
     *
     * @param \Closure(): T $work
     *
     * @return T
     */
    public function run(\Closure $work): mixed;
}
