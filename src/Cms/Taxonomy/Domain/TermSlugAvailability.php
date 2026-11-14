<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

/**
 * Checks that no other term of the same taxonomy is addressed by the slug of
 * the given one, either the slug it is edited with or the one it has on the
 * site, so two terms never share an address. A term without a slug takes
 * none.
 *
 * The unique indexes on the slug columns still guard against two saves
 * racing, though only each column on its own.
 */
final readonly class TermSlugAvailability
{
    public function __construct(private Term $term) {}

    public function __invoke(TermRepository $termRepository): void
    {
        $slug = $this->term->slug();

        if (null === $slug) {
            return;
        }

        $owner = $termRepository->findBySlug($this->term->taxonomy(), $slug);

        if (null !== $owner && !$owner->id()->equals($this->term->id())) {
            throw TermSlugAlreadyTaken::bySlug($slug);
        }
    }
}
