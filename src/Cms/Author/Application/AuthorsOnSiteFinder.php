<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Shared\Domain\DomainError;

/**
 * Finds the authors on the site among those given, read at once, as the
 * site shows them; one off it, an id of none, or what can't be an
 * id, is left out.
 */
final readonly class AuthorsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, AuthorOnSiteResponse> keyed by id
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
            $onSite = AuthorOnSiteResponse::fromAuthor($author);

            if (null !== $onSite) {
                $found[$author->id()->value()] = $onSite;
            }
        }

        return $found;
    }
}
