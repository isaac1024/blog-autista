<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Domain;

use Cms\Author\Domain\AuthorId;

final class AuthorIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    public static function create(string $value = self::DEFAULT_VALUE): AuthorId
    {
        return new AuthorId($value);
    }

    /**
     * An AuthorId with a fresh random value, for tests where the id doesn't
     * matter but has to be unique.
     */
    public static function random(): AuthorId
    {
        return AuthorId::random();
    }
}
