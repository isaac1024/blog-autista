<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Post;

use Cms\Post\Application\PostsUnderSectionSlugCounter;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\PostsUnderSectionSlugs;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the posts of the CMS which of them, on the
 * site, carry the slug of a section in their address.
 */
#[AsAlias(PostsUnderSectionSlugs::class)]
final readonly class CmsPostsUnderSectionSlugs implements PostsUnderSectionSlugs
{
    public function __construct(private PostRepository $postRepository) {}

    #[\Override]
    public function countedBySection(Slug $slug): array
    {
        return new PostsUnderSectionSlugCounter($slug->value())($this->postRepository);
    }
}
