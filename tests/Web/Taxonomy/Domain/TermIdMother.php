<?php

declare(strict_types=1);

namespace Web\Tests\Taxonomy\Domain;

use Web\Taxonomy\Domain\TermId;

/**
 * Builds TermId value objects for tests:
 *
 *     TermIdMother::create()
 *     TermIdMother::random()
 */
final class TermIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    public static function create(string $value = self::DEFAULT_VALUE): TermId
    {
        return new TermId($value);
    }

    public static function random(): TermId
    {
        return TermId::random();
    }
}
