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
        private string $credentialsVersion,
        private array $roles,
    ) {}

    /**
     * What the session keeps of it, the hash of its password aside: a copy
     * of the session, such as a backup, gives no hash to crack. Symfony
     * compares it with the user it loads again on each request through
     * isEqualTo(), which never reads the hash.
     *
     * @return array{identifier: string, credentialsVersion: string, roles: list<string>}
     */
    public function __serialize(): array
    {
        return ['identifier' => $this->identifier, 'credentialsVersion' => $this->credentialsVersion, 'roles' => $this->roles];
    }

    /**
     * @param array{identifier: string, credentialsVersion: string, roles: list<string>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->identifier = $data['identifier'];
        $this->passwordHash = '';
        $this->credentialsVersion = $data['credentialsVersion'];
        $this->roles = $data['roles'];
    }

    public static function fromAdministrator(Administrator $administrator): self
    {
        return new self($administrator->identifier(), $administrator->passwordHash(), $administrator->credentialsVersion(), $administrator->roles());
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

    public function credentialsVersion(): string
    {
        return $this->credentialsVersion;
    }

    #[\Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $this->identifier === $user->identifier
            && $this->credentialsVersion === $user->credentialsVersion
            && $this->roles === $user->roles;
    }
}
