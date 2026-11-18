<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\DomainError;

/**
 * Finds the names of the authors among the ids given, as edited, without
 * reading the rest of each: an id of none, or anything that can't be an id,
 * is left out.
 */
final readonly class AuthorNamesFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, string> by id
     */
    public function __invoke(AuthorRepository $authorRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new AuthorId($id);
            } catch (DomainError) {
                // Not the id of one: none to find.
            }
        }

        return $authorRepository->namesOf($ids);
    }
}
