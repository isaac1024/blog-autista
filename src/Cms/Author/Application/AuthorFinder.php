<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;

/**
 * Finds an author by its id, failing when it doesn't exist.
 */
final readonly class AuthorFinder
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository): AuthorResponse
    {
        $author = $authorRepository->find(new AuthorId($this->id)) ?? throw AuthorNotFound::withId($this->id);

        return AuthorResponse::fromAuthor($author);
    }
}
