<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

/**
 * Checks that no other author is addressed by the slug of the given one,
 * either the slug it is edited with or the one it has on the site, so two
 * authors never share an address. An author without a slug takes none.
 *
 * The unique indexes on the slug columns still guard against two saves
 * racing, though only each column on its own.
 */
final readonly class AuthorSlugAvailability
{
    public function __construct(private Author $author) {}

    public function __invoke(AuthorRepository $authorRepository): void
    {
        $slug = $this->author->slug();

        if (null === $slug) {
            return;
        }

        $owner = $authorRepository->findBySlug($slug);

        if (null !== $owner && !$owner->id()->equals($this->author->id())) {
            throw AuthorSlugAlreadyTaken::bySlug($slug);
        }
    }
}
