<?php

declare(strict_types=1);

namespace Web\Tests\External\Domain;

use Web\External\Domain\ExternalResourceId;

/**
 * Builds ExternalResourceId value objects for tests:
 *
 *     ExternalResourceIdMother::create()
 *     ExternalResourceIdMother::random()
 */
final class ExternalResourceIdMother
{
    public const string DEFAULT_VALUE = '01a1e2a0-0000-7000-8000-0000000000b1';

    public static function create(string $value = self::DEFAULT_VALUE): ExternalResourceId
    {
        return new ExternalResourceId($value);
    }

    public static function random(): ExternalResourceId
    {
        return ExternalResourceId::random();
    }
}
