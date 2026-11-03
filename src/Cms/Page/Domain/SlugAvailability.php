<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

/**
 * Checks that no other page is addressed by the slug of the given one, so two
 * pages never share an address.
 *
 * The unique index on the slug column still guards against two saves racing.
 */
final readonly class SlugAvailability
{
    public function __construct(private Page $page) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $owner = $pageRepository->findBySlug($this->page->slug());

        if (null !== $owner && !$owner->id()->equals($this->page->id())) {
            throw SlugAlreadyTaken::bySlug($this->page->slug());
        }
    }
}
