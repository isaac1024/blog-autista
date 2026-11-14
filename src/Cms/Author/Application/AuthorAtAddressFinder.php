<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\AuthorRepository;
use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

/**
 * Finds the author at an address of the site by its slug: the one on the
 * site with it, or the one off the site whose last address it was. A slug
 * that can't be one has no author.
 */
final readonly class AuthorAtAddressFinder
{
    public function __construct(private string $slug) {}

    public function onSite(AuthorRepository $authorRepository): ?AuthorResponse
    {
        $slug = $this->slug();
        $author = null === $slug ? null : $authorRepository->findOnSiteBySlug($slug);

        return null === $author ? null : AuthorResponse::fromAuthor($author);
    }

    public function lastOffSite(AuthorRepository $authorRepository): ?AuthorResponse
    {
        $slug = $this->slug();
        $author = null === $slug ? null : $authorRepository->findOffSiteByLastSiteSlug($slug);

        return null === $author ? null : AuthorResponse::fromAuthor($author);
    }

    private function slug(): ?Slug
    {
        try {
            return new Slug($this->slug);
        } catch (DomainError) {
            return null;
        }
    }
}
