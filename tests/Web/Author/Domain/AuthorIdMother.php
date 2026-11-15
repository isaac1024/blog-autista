<?php

declare(strict_types=1);

namespace Web\Tests\Author\Domain;

use Web\Author\Domain\AuthorId;

/**
 * Builds AuthorId value objects for tests:
 *
 *     AuthorIdMother::create()
 *     AuthorIdMother::random()
 */
final class AuthorIdMother
{
    public const string DEFAULT_VALUE = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02';

    public static function create(string $value = self::DEFAULT_VALUE): AuthorId
    {
        return new AuthorId($value);
    }

    public static function random(): AuthorId
    {
        return AuthorId::random();
    }
}
