<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostRepository;
use Cms\Shared\Domain\Slug;

/**
 * Counts the posts on the site whose address carries the slug of a section,
 * by the section each is under there.
 */
final readonly class PostsUnderSectionSlugCounter
{
    public function __construct(private string $slug) {}

    /**
     * @return array<string, int> by the id of the section
     */
    public function __invoke(PostRepository $postRepository): array
    {
        return $postRepository->onSiteUnderSectionSlug(new Slug($this->slug));
    }
}
