<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Checks that no other page is addressed by the slug of the given one, either
 * the slug it is edited with or the one it has on the site, so two pages
 * never share an address. A page without a slug takes none.
 *
 * The unique indexes on the slug columns still guard against two saves
 * racing, though only each column on its own.
 */
final readonly class PageSlugAvailability
{
    public function __construct(private Page $page) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $slug = $this->page->slug();

        if (null === $slug) {
            return;
        }

        $owner = $pageRepository->findBySlug($slug);

        if (null !== $owner && !$owner->id()->equals($this->page->id())) {
            throw PageSlugAlreadyTaken::bySlug($slug);
        }
    }
}
