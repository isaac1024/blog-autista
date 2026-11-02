<?php

declare(strict_types=1);

namespace Cms\Authentication\Application;

use Cms\Authentication\Domain\Administrator;
use Cms\Authentication\Domain\AdministratorRepository;

/**
 * Read use cases around the CMS administrator.
 */
final readonly class AdministratorFinder
{
    public function __construct(private AdministratorRepository $administrators) {}

    public function find(string $identifier): Administrator
    {
        return $this->administrators->findByIdentifier($identifier)
            ?? throw AdministratorNotFound::withIdentifier($identifier);
    }

    public function exists(string $identifier): bool
    {
        return $this->administrators->existsByIdentifier($identifier);
    }
}
