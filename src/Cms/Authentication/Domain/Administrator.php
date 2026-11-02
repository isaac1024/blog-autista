<?php

declare(strict_types=1);

namespace Cms\Authentication\Domain;

/**
 * The administrator that can sign in to the CMS.
 */
final readonly class Administrator
{
    public const string ADMIN_ROLE = 'ROLE_CMS_ADMIN';

    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $identifier,
        private string $passwordHash,
        private array $roles = [self::ADMIN_ROLE],
    ) {
        if ('' === trim($identifier)) {
            throw new \InvalidArgumentException('The administrator identifier must not be empty.');
        }

        if ('' === $passwordHash) {
            throw new \InvalidArgumentException('The administrator password hash must not be empty.');
        }

        if ([] === $roles) {
            throw new \InvalidArgumentException('The administrator must have at least one role.');
        }
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->roles;
    }
}
