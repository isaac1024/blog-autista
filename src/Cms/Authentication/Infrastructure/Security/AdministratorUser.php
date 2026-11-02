<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Security;

use Cms\Authentication\Domain\Administrator;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Inbound adapter that exposes the domain administrator to Symfony Security.
 */
final readonly class AdministratorUser implements EquatableInterface, PasswordAuthenticatedUserInterface, UserInterface
{
    /**
     * @param list<string> $roles
     */
    private function __construct(
        private string $identifier,
        private string $passwordHash,
        private array $roles,
    ) {}

    public static function fromAdministrator(Administrator $administrator): self
    {
        return new self($administrator->identifier(), $administrator->passwordHash(), $administrator->roles());
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        $identifier = $this->identifier;
        \assert('' !== $identifier);

        return $identifier;
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->identifier === $user->identifier
            && $this->roles === $user->roles;
    }
}
