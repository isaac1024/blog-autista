<?php

declare(strict_types=1);

namespace Web\Author\Domain;

/**
 * Outbound port that makes and removes the copies of an avatar the site
 * serves, from the file the CMS keeps.
 */
interface AvatarCopies
{
    /**
     * Makes the copies of the file the CMS keeps at the path, under the name
     * given, those not made yet: copies of a name never change.
     */
    public function publish(string $path, string $name): void;

    /**
     * Removes the copies of the name, if any.
     */
    public function remove(string $name): void;
}
