<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Domain;

use Cms\Authentication\Domain\Administrator;

/**
 * Builds Administrator aggregates for tests, with sensible defaults that can be
 * overridden through named arguments:
 *
 *     AdministratorMother::create()
 *     AdministratorMother::create(identifier: 'editor')
 */
final class AdministratorMother
{
    public const string DEFAULT_IDENTIFIER = 'admin';
    public const string DEFAULT_PASSWORD_HASH = 'password-hash';

    /**
     * @param list<string> $roles
     */
    public static function create(
        string $identifier = self::DEFAULT_IDENTIFIER,
        string $passwordHash = self::DEFAULT_PASSWORD_HASH,
        array $roles = [Administrator::ADMIN_ROLE],
    ): Administrator {
        return new Administrator($identifier, $passwordHash, $roles);
    }
}
