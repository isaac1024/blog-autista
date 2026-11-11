<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Domain;

use Web\Analytics\Domain\VisitId;

/**
 * Builds VisitId value objects for tests:
 *
 *     VisitIdMother::create()
 *     VisitIdMother::create('01a1b801-ce58-723d-8234-b76d9105e302')
 *     VisitIdMother::random()
 */
final class VisitIdMother
{
    public const string DEFAULT_VALUE = '01a1b801-ce58-723d-8234-b76d9105e301';

    public static function create(string $value = self::DEFAULT_VALUE): VisitId
    {
        return new VisitId($value);
    }

    /**
     * A VisitId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): VisitId
    {
        return VisitId::random();
    }
}
