<?php

declare(strict_types=1);

namespace Web\Redirect\Domain;

/**
 * Outbound port that tells where a resource of the site is served now, so a
 * redirect leads to its current address, whatever it was when the
 * redirect was saved.
 */
interface TargetAddresses
{
    /**
     * The address of the resource on the site, relative to its root, or
     * null while it isn't on it. An image is given at the width, or at its
     * largest when none is asked for.
     *
     * @param string $type "page", "image", "post", "term" (a section or a tag) or "author"
     */
    public function addressOf(string $type, string $id, ?int $width): ?string;
}
