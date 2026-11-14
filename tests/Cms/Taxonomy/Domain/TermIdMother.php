<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Domain;

use Cms\Taxonomy\Domain\TermId;

final class TermIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    public static function create(string $value = self::DEFAULT_VALUE): TermId
    {
        return new TermId($value);
    }

    /**
     * A TermId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): TermId
    {
        return TermId::random();
    }
}
