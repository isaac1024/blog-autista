<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Persistence;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Infrastructure\Persistence\EnvironmentAdministratorRepository;
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
    }

    private function repository(string $identifier, string $password): EnvironmentAdministratorRepository
    {
        return new EnvironmentAdministratorRepository(
            $identifier,
            $password,
            new PasswordHasherFactory([
                PasswordAuthenticatedUserInterface::class => ['algorithm' => 'plaintext'],
            ]),
        );
    }
}
