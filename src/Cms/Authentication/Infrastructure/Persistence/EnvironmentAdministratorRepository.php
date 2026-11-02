<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Persistence;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Domain\AdministratorRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Outbound adapter that reads the administrator from environment variables.
 *
 * The plain password never leaves this adapter: the domain only sees its hash.
 */
#[AsAlias(AdministratorRepository::class)]
final readonly class EnvironmentAdministratorRepository implements AdministratorRepository
{
    public function __construct(
        #[Autowire('%env(CMS_USER_IDENTIFIER)%')]
        private string $identifier,
        #[Autowire('%env(CMS_USER_PASSWORD)%')]
        private string $password,
        private PasswordHasherFactoryInterface $passwordHashers,
    ) {
        if ('' === $identifier || '' === $password) {
            throw new \InvalidArgumentException('CMS_USER_IDENTIFIER and CMS_USER_PASSWORD must not be empty.');
        }
    }

    #[\Override]
    public function findByIdentifier(string $identifier): ?Administrator
    {
        if (!$this->existsByIdentifier($identifier)) {
            return null;
        }

        return new Administrator($this->identifier, $this->hashPassword());
    }

    #[\Override]
    public function existsByIdentifier(string $identifier): bool
    {
        return 0 === strcasecmp($this->identifier, $identifier);
    }

    private function hashPassword(): string
    {
        return $this->passwordHashers
            ->getPasswordHasher(PasswordAuthenticatedUserInterface::class)
            ->hash($this->password);
    }
}
