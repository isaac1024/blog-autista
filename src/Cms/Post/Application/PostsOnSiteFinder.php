<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;

/**
 * Finds the posts on the site among those given, as the site has them; one
 * off it, or an id of no post, is left out.
 */
final readonly class PostsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, PostOnSiteResponse> keyed by id
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $posts = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $post = $postRepository->find(new PostId($id));
            } catch (DomainError) {
                // Not the id of a post: none on the site.
                continue;
            }

            $version = $post?->onSite();
            $address = $post?->publishedAddress();
            $section = $version?->classification()->section();
            $firstPublishedAt = $post?->timeline()->firstPublishedAt();

            if (null !== $version && null !== $address && null !== $section && null !== $firstPublishedAt) {
                $posts[$id] = new PostOnSiteResponse(
                    $version->title()->value(),
                    PostAddressResponse::fromAddress($address),
                    $version->seo()->description(),
                    $section->value(),
                    $version->openingImage()?->value(),
                    $firstPublishedAt,
                );
            }
        }

        return $posts;
    }
}
