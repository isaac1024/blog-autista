<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Persistence;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Infrastructure\Persistence\EnvironmentAdministratorRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        $this->expectExceptionObject(new \InvalidArgumentException('CMS_USER_IDENTIFIER and CMS_USER_PASSWORD_HASH must not be empty.'));

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
        yield 'no hash of a password' => ['admin', ''];
    }

    public function testItRejectsAnIdentifierMadeOfWhitespace(): void
    {
        $repository = $this->repository('   ', 'secret');

        $this->expectExceptionObject(new \InvalidArgumentException('The administrator identifier must not be empty.'));

        $repository->findByIdentifier('   ');
    }

    public function testItChangesTheCredentialsVersionWhenTheHashOfThePasswordChanges(): void
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

    /**
     * Production refuses the hashes of the development password that .env.dev
     * and .env.test hold, and takes any other.
     */
    #[DataProvider('developmentHashes')]
    public function testItRefusesTheDevelopmentPasswordInProduction(string $hash): void
    {
        self::assertNotNull($this->repository('admin', password_hash('a strong one', \PASSWORD_BCRYPT, ['cost' => 4]), environment: 'prod')->findByIdentifier('admin'));
        // Elsewhere, it is the password to sign in with.
        self::assertNotNull($this->repository('admin', $hash, environment: 'dev')->findByIdentifier('admin'));

        $this->expectExceptionObject(new \InvalidArgumentException('CMS_USER_PASSWORD_HASH must not hash the development password in production.'));

        $this->repository('admin', $hash, environment: 'prod');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function developmentHashes(): iterable
    {
        yield 'that of development' => ['$2y$13$BVXccXGxMfru5Fr7EfMhGuNjQbibjpz3geft3C/VW0n32g2qeBcOO'];
        yield 'that of the tests' => ['$2y$04$6qennMPep9vsjkS7KyHYKupBTN9S3l1ciCHx9zuLTwg7YDOclIj3S'];
    }

    private function repository(string $identifier, string $passwordHash, string $appSecret = 'test-app-secret', string $environment = 'test'): EnvironmentAdministratorRepository
    {
        return new EnvironmentAdministratorRepository($identifier, $passwordHash, $appSecret, $environment);
    }
}
