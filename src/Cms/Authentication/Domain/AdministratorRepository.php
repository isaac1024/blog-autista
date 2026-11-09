<?php

declare(strict_types=1);

namespace Cms\Authentication\Domain;

/**
 * Outbound port used to retrieve the CMS administrator.
 */
interface AdministratorRepository
{
    public function findByIdentifier(string $identifier): ?Administrator;

    public function existsByIdentifier(string $identifier): bool;

    public function credentialsVersion(string $identifier): ?string;
}
