<?php

declare(strict_types=1);

namespace Web\Tests\Post\Domain;

use Web\Post\Domain\PostId;

/**
 * Builds PostId value objects for tests:
 *
 *     PostIdMother::create()
 *     PostIdMother::random()
 */
final class PostIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1b01';

    public static function create(string $value = self::DEFAULT_VALUE): PostId
    {
        return new PostId($value);
    }

    public static function random(): PostId
    {
        return PostId::random();
    }
}
