<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Security;

use Cms\Authentication\Application\AdministratorFinder;
use Cms\Authentication\Application\AdministratorNotFound;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Inbound adapter that lets Symfony Security load the CMS administrator.
 *
 * @implements UserProviderInterface<AdministratorUser>
 */
final readonly class AdministratorUserProvider implements UserProviderInterface
{
    public function __construct(private AdministratorFinder $administrators) {}

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            return AdministratorUser::fromAdministrator($this->administrators->find($identifier));
        } catch (AdministratorNotFound) {
            throw $this->userNotFound($identifier);
        }
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AdministratorUser) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        try {
            $version = $this->administrators->credentialsVersion($user->getUserIdentifier());
        } catch (AdministratorNotFound) {
            throw $this->userNotFound($user->getUserIdentifier());
        }

        if (!hash_equals($version, $user->credentialsVersion())) {
            throw $this->userNotFound($user->getUserIdentifier());
        }

        return $user;
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return AdministratorUser::class === $class;
    }

    private function userNotFound(string $identifier): UserNotFoundException
    {
        $exception = new UserNotFoundException(\sprintf('User "%s" does not exist.', $identifier));
        $exception->setUserIdentifier($identifier);

        return $exception;
    }
}
