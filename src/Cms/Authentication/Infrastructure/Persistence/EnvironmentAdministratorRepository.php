<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Persistence;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Domain\AdministratorRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Outbound adapter that reads the administrator from environment variables:
 * their identifier, and the hash of their password, which
 * `bin/console security:hash-password` makes and the secrets vault keeps in
 * production. The password itself is kept nowhere.
 */
#[AsAlias(AdministratorRepository::class)]
final readonly class EnvironmentAdministratorRepository implements AdministratorRepository
{
    /**
     * The hashes of the development password, "admin", that .env.dev and
     * .env.test hold: production refuses them.
     */
    private const array DEVELOPMENT_HASHES = [
        '$2y$13$BVXccXGxMfru5Fr7EfMhGuNjQbibjpz3geft3C/VW0n32g2qeBcOO',
        '$2y$04$6qennMPep9vsjkS7KyHYKupBTN9S3l1ciCHx9zuLTwg7YDOclIj3S',
    ];

    public function __construct(
        #[Autowire('%env(CMS_USER_IDENTIFIER)%')]
        private string $identifier,
        #[Autowire('%env(CMS_USER_PASSWORD_HASH)%')]
        private string $passwordHash,
        #[Autowire('%env(APP_SECRET)%')]
        private string $appSecret,
        #[Autowire('%kernel.environment%')]
        string $environment,
    ) {
        if ('' === $identifier || '' === $passwordHash) {
            throw new \InvalidArgumentException('CMS_USER_IDENTIFIER and CMS_USER_PASSWORD_HASH must not be empty.');
        }

        if ('' === $appSecret) {
            throw new \InvalidArgumentException('APP_SECRET must not be empty.');
        }

        if ('prod' === $environment && \in_array($passwordHash, self::DEVELOPMENT_HASHES, true)) {
            throw new \InvalidArgumentException('CMS_USER_PASSWORD_HASH must not hash the development password in production.');
        }
    }

    #[\Override]
    public function findByIdentifier(string $identifier): ?Administrator
    {
        if (!$this->existsByIdentifier($identifier)) {
            return null;
        }

        return new Administrator($this->identifier, $this->passwordHash, $this->currentCredentialsVersion());
    }

    #[\Override]
    public function existsByIdentifier(string $identifier): bool
    {
        return 0 === strcasecmp($this->identifier, $identifier);
    }

    #[\Override]
    public function credentialsVersion(string $identifier): ?string
    {
        if (!$this->existsByIdentifier($identifier)) {
            return null;
        }

        return $this->currentCredentialsVersion();
    }

    /**
     * Changes with the identifier and with the password, whose hash changes
     * with it, so either signs out every session.
     */
    private function currentCredentialsVersion(): string
    {
        return hash_hmac('sha256', $this->identifier."\0".$this->passwordHash, $this->appSecret);
    }
}
