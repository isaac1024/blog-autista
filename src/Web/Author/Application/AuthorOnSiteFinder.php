<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;

/**
 * Finds an author on the site by its id; none while it is off it.
 */
final readonly class AuthorOnSiteFinder
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository): ?AuthorResponse
    {
        $author = $authorRepository->find(new AuthorId($this->id));

        return null === $author ? null : AuthorResponse::fromAuthor($author);
    }
}
