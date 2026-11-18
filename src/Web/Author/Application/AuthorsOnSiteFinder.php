<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Shared\Domain\DomainError;
use Web\Author\Domain\AuthorId;
use Web\Author\Domain\AuthorRepository;

/**
 * Finds the authors on the site among those given, read at once; one off
 * the site, or what can't be the id of one, is left out.
 */
final readonly class AuthorsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, AuthorResponse> keyed by id
     */
    public function __invoke(AuthorRepository $authorRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new AuthorId($id);
            } catch (DomainError) {
                // Not the id of one: none on the site.
            }
        }

        $found = [];

        foreach ($authorRepository->findByIds($ids) as $author) {
            $found[$author->id()->value()] = AuthorResponse::fromAuthor($author);
        }

        return $found;
    }
}
