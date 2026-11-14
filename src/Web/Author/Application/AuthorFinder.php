<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Web\Author\Domain\AuthorRepository;

/**
 * Finds the author on the site at a slug.
 */
final readonly class AuthorFinder
{
    public function __construct(private string $slug) {}

    public function __invoke(AuthorRepository $authorRepository): AuthorResponse
    {
        $author = $authorRepository->findBySlug($this->slug) ?? throw AuthorNotFound::withSlug($this->slug);

        return AuthorResponse::fromAuthor($author);
    }
}
