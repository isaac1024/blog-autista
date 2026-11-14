<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\DomainError;

/**
 * Finds the authors among the ids given, leaving out those of no author,
 * and anything that can't be the id of one.
 */
final readonly class AuthorsFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<AuthorResponse>
     */
    public function __invoke(AuthorRepository $authorRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new AuthorId($id);
            } catch (DomainError) {
                // Not the id of an author: none to find.
            }
        }

        return array_map(AuthorResponse::fromAuthor(...), $authorRepository->findByIds($ids));
    }
}
