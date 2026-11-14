<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Domain;

use Cms\Post\Domain\PostId;

final class PostIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1b01';

    public static function create(string $value = self::DEFAULT_VALUE): PostId
    {
        return new PostId($value);
    }

    /**
     * A PostId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): PostId
    {
        return PostId::random();
    }
}
