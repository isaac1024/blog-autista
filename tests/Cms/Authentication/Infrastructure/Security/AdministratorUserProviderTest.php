<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Security;

use Cms\Authentication\Application\AdministratorFinder;
use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Domain\AdministratorRepository;
use Cms\Authentication\Infrastructure\Security\AdministratorUserProvider;
use Cms\Tests\Authentication\Domain\AdministratorMother;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class AdministratorUserProviderTest extends TestCase
{
    public function testItAdaptsTheAdministratorToASecurityUser(): void
    {
        $provider = $this->provider(AdministratorMother::create());

        $user = $provider->loadUserByIdentifier(AdministratorMother::DEFAULT_IDENTIFIER);

        self::assertSame(AdministratorMother::DEFAULT_IDENTIFIER, $user->getUserIdentifier());
        self::assertSame(AdministratorMother::DEFAULT_PASSWORD_HASH, $user->getPassword());
        self::assertSame([Administrator::ADMIN_ROLE], $user->getRoles());
    }

    public function testItRejectsAnUnknownIdentifier(): void
    {
        $provider = $this->provider(null);

        try {
            $provider->loadUserByIdentifier('someone-else');
            self::fail('An unknown identifier must not load a user.');
        } catch (UserNotFoundException $exception) {
            // Symfony reads it back, e.g. to keep the last username in the login form.
            self::assertSame('someone-else', $exception->getUserIdentifier());
        }
    }

    public function testItKeepsTheUserStoredInTheSession(): void
    {
        $provider = $this->provider(AdministratorMother::create());

        $user = $provider->loadUserByIdentifier(AdministratorMother::DEFAULT_IDENTIFIER);

        self::assertSame($user, $provider->refreshUser($user));
    }

    public function testItRejectsTheSessionOfAnAdministratorNoLongerConfigured(): void
    {
        $user = $this->provider(AdministratorMother::create())
            ->loadUserByIdentifier(AdministratorMother::DEFAULT_IDENTIFIER);

        $this->expectException(UserNotFoundException::class);

        $this->provider(null)->refreshUser($user);
    }

    public function testItRejectsTheSessionWhenTheCredentialsChange(): void
    {
        $user = $this->provider(AdministratorMother::create(credentialsVersion: 'before'))
            ->loadUserByIdentifier(AdministratorMother::DEFAULT_IDENTIFIER);
        $provider = $this->provider(AdministratorMother::create(credentialsVersion: 'after'));

        $this->expectException(UserNotFoundException::class);

        $provider->refreshUser($user);
    }

    /**
     * Only the administrators it provides are refreshed from the session.
     */
    public function testItRefusesToRefreshAUserItDoesNotProvide(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->provider(null)->refreshUser(new InMemoryUser('admin', null));
    }

    private function provider(?Administrator $administrator): AdministratorUserProvider
    {
        $repository = new readonly class($administrator) implements AdministratorRepository {
            public function __construct(private ?Administrator $administrator) {}

            #[\Override]
            public function findByIdentifier(string $identifier): ?Administrator
            {
                return $this->administrator;
            }

            #[\Override]
            public function existsByIdentifier(string $identifier): bool
            {
                return null !== $this->administrator;
            }

            #[\Override]
            public function credentialsVersion(string $identifier): ?string
            {
                return $this->administrator?->credentialsVersion();
            }
        };

        return new AdministratorUserProvider(new AdministratorFinder($repository));
    }
}
