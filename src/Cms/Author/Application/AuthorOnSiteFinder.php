<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;

/**
 * Finds an author as it is on the site; none while it is off it, or when
 * there is no such author.
 */
final readonly class AuthorOnSiteFinder
{
    public function __construct(private string $id) {}

    public function __invoke(AuthorRepository $authorRepository): ?AuthorOnSiteResponse
    {
        $version = $authorRepository->find(new AuthorId($this->id))?->onSite();
        $slug = $version?->slug();

        return null === $version || null === $slug
            ? null
            : new AuthorOnSiteResponse($version->name()->value(), $slug->value());
    }
}
