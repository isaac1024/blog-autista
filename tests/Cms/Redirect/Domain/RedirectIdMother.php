<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Domain;

use Cms\Redirect\Domain\RedirectId;

/**
 * Builds RedirectId value objects for tests:
 *
 *     RedirectIdMother::create()
 *     RedirectIdMother::create('0196f3a0-0000-7000-8000-0000000000e2')
 *     RedirectIdMother::random()
 */
final class RedirectIdMother
{
    public const string DEFAULT_VALUE = '0196f3a0-0000-7000-8000-0000000000e1';

    public static function create(string $value = self::DEFAULT_VALUE): RedirectId
    {
        return new RedirectId($value);
    }

    /**
     * A RedirectId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): RedirectId
    {
        return RedirectId::random();
    }
}
