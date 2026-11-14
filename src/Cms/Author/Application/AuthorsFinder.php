<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;

/**
 * Finds the authors among the ids given, leaving out those of no author.
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
        return array_map(
            AuthorResponse::fromAuthor(...),
            $authorRepository->findByIds(array_map(static fn (string $id): AuthorId => new AuthorId($id), $this->ids)),
        );
    }
}
