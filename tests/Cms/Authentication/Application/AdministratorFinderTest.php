<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Application;

use Cms\Authentication\Application\AdministratorFinder;
use Cms\Authentication\Application\AdministratorNotFound;
use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Domain\AdministratorRepository;
use Cms\Tests\Authentication\Domain\AdministratorMother;
use PHPUnit\Framework\TestCase;

final class AdministratorFinderTest extends TestCase
{
    public function testItFindsAnExistingAdministrator(): void
    {
        $administrator = AdministratorMother::create();
        $finder = new AdministratorFinder($this->repositoryReturning($administrator));

        self::assertSame($administrator, $finder->find(AdministratorMother::DEFAULT_IDENTIFIER));
        self::assertTrue($finder->exists(AdministratorMother::DEFAULT_IDENTIFIER));
    }

    public function testItThrowsWhenTheAdministratorDoesNotExist(): void
    {
        $finder = new AdministratorFinder($this->repositoryReturning(null));

        self::assertFalse($finder->exists(AdministratorMother::DEFAULT_IDENTIFIER));

        $this->expectException(AdministratorNotFound::class);
        $finder->find(AdministratorMother::DEFAULT_IDENTIFIER);
    }

    private function repositoryReturning(?Administrator $administrator): AdministratorRepository
    {
        return new readonly class($administrator) implements AdministratorRepository {
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
    }
}
