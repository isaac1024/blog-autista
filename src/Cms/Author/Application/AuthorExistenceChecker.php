<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;

/**
 * Tells whether there is an author with the id, on the site or not.
 */
final readonly class AuthorExistenceChecker
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository): bool
    {
        return null !== $authorRepository->find(new AuthorId($this->id));
    }
}
