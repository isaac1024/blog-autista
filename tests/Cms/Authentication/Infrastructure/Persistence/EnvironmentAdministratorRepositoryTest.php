<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Persistence;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Infrastructure\Persistence\EnvironmentAdministratorRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class EnvironmentAdministratorRepositoryTest extends TestCase
{
    public function testItLoadsTheAdministratorConfiguredInTheEnvironment(): void
    {
        $repository = $this->repository('admin', 'secret');

        $administrator = $repository->findByIdentifier('Admin');

        self::assertNotNull($administrator);
        self::assertSame('admin', $administrator->identifier());
        self::assertSame('secret', $administrator->passwordHash());
        self::assertSame([Administrator::ADMIN_ROLE], $administrator->roles());
        self::assertTrue($repository->existsByIdentifier('ADMIN'));
    }

    public function testItReturnsNothingForAnUnknownIdentifier(): void
    {
        $repository = $this->repository('admin', 'secret');

        self::assertNull($repository->findByIdentifier('someone-else'));
        self::assertFalse($repository->existsByIdentifier('someone-else'));
        self::assertNull($repository->credentialsVersion('someone-else'));
    }

    #[DataProvider('incompleteCredentials')]
    public function testItRefusesToStartWithIncompleteCredentials(string $identifier, string $password): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('CMS_USER_IDENTIFIER and CMS_USER_PASSWORD must not be empty.'));

        $this->repository($identifier, $password);
    }

    /**
     * Either one missing is enough: a CMS must never run without a password.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function incompleteCredentials(): iterable
    {
        yield 'no identifier' => ['', 'secret'];
        yield 'no password' => ['admin', ''];
    }

    public function testItRejectsAnIdentifierMadeOfWhitespace(): void
    {
        $repository = $this->repository('   ', 'secret');

        $this->expectExceptionObject(new \InvalidArgumentException('The administrator identifier must not be empty.'));

        $repository->findByIdentifier('   ');
    }

    public function testItChangesTheCredentialsVersionWhenThePasswordChanges(): void
    {
        $before = $this->repository('admin', 'secret')->credentialsVersion('admin');
        $after = $this->repository('admin', 'new-secret')->credentialsVersion('admin');

        self::assertNotNull($before);
        self::assertNotNull($after);
        self::assertNotSame($before, $after);
    }

    public function testItChangesTheCredentialsVersionWhenTheApplicationSecretChanges(): void
    {
        $before = $this->repository('admin', 'secret')->credentialsVersion('admin');
        $after = $this->repository('admin', 'secret', appSecret: 'another-app-secret')->credentialsVersion('admin');

        self::assertNotNull($before);
        self::assertNotNull($after);
        self::assertNotSame($before, $after);
    }

    public function testItRefusesAnEmptyApplicationSecret(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('APP_SECRET must not be empty.'));

        $this->repository('admin', 'secret', appSecret: '');
    }

    public function testItRefusesTheDevelopmentPasswordInProduction(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('CMS_USER_PASSWORD must not use the development default in production.'));

        $this->repository('admin', 'admin', environment: 'prod');
    }

    private function repository(string $identifier, string $password, string $appSecret = 'test-app-secret', string $environment = 'test'): EnvironmentAdministratorRepository
    {
        return new EnvironmentAdministratorRepository(
            $identifier,
            $password,
            new PasswordHasherFactory([
                PasswordAuthenticatedUserInterface::class => ['algorithm' => 'plaintext'],
            ]),
            $appSecret,
            $environment,
        );
    }
}
