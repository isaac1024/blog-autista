<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Security;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Infrastructure\Security\AdministratorUser;
use Cms\Tests\Authentication\Domain\AdministratorMother;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Symfony compares the user in the session with the refreshed one through
 * isEqualTo(): anything other than the same administrator must end the session.
 */
final class AdministratorUserTest extends TestCase
{
    /**
     * A copy of the session gives no hash to crack, and its user is still
     * the administrator Symfony loads again.
     */
    public function testTheSessionKeepsTheAdministratorWithoutTheHashOfItsPassword(): void
    {
        $administrator = AdministratorMother::create(passwordHash: '$2y$13$hashOfThePassword');

        $kept = serialize(AdministratorUser::fromAdministrator($administrator));
        $user = unserialize($kept);

        self::assertStringNotContainsString('hashOfThePassword', $kept);
        self::assertInstanceOf(AdministratorUser::class, $user);
        self::assertTrue($user->isEqualTo(AdministratorUser::fromAdministrator($administrator)));
        self::assertSame([AdministratorMother::DEFAULT_IDENTIFIER, [Administrator::ADMIN_ROLE]], [$user->getUserIdentifier(), $user->getRoles()]);
    }

    public function testItEqualsTheSameAdministrator(): void
    {
        $user = AdministratorUser::fromAdministrator(AdministratorMother::create());

        self::assertTrue($user->isEqualTo(AdministratorUser::fromAdministrator(AdministratorMother::create())));
    }

    public function testItDiffersFromAnotherAdministratorWithTheSameRoles(): void
    {
        $user = AdministratorUser::fromAdministrator(AdministratorMother::create(identifier: 'admin'));

        self::assertFalse($user->isEqualTo(AdministratorUser::fromAdministrator(AdministratorMother::create(identifier: 'editor'))));
    }

    public function testItDiffersWhenTheRolesChange(): void
    {
        $user = AdministratorUser::fromAdministrator(AdministratorMother::create());

        self::assertFalse($user->isEqualTo(AdministratorUser::fromAdministrator(AdministratorMother::create(roles: ['ROLE_EDITOR']))));
    }

    public function testItDiffersWhenTheCredentialsVersionChanges(): void
    {
        $user = AdministratorUser::fromAdministrator(AdministratorMother::create(credentialsVersion: 'before'));

        self::assertFalse($user->isEqualTo(AdministratorUser::fromAdministrator(AdministratorMother::create(credentialsVersion: 'after'))));
    }

    public function testItDiffersFromAnotherKindOfUser(): void
    {
        $user = AdministratorUser::fromAdministrator(AdministratorMother::create());
        $other = new InMemoryUser(AdministratorMother::DEFAULT_IDENTIFIER, AdministratorMother::DEFAULT_PASSWORD_HASH, $user->getRoles());

        self::assertFalse($user->isEqualTo($other));
    }
}
