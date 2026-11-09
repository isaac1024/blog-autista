<?php

declare(strict_types=1);

namespace Web\Page\Domain;

use Shared\Domain\DomainError;

/**
 * Another page on the site still has the address a page is published at.
 *
 * The CMS never publishes two pages at the same address, so the site only
 * meets this while the event that frees it, the other page's unpublication
 * or new address, has yet to arrive.
 */
final class SlugTaken extends DomainError
{
    public static function withSlug(string $slug): self
    {
        return new self(\sprintf('Another page on the site is still at "%s".', $slug), 'errors.page.slug.taken', ['%slug%' => $slug]);
    }
}
