<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;

/**
 * Finds the posts on the site among those given, read at once, as cards of
 * what the site has of them; one off it, or an id of no post, is left out.
 */
final readonly class PostsOnSiteFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, PostOnSiteCardResponse> keyed by id
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $ids = [];

        foreach ($this->ids as $id) {
            try {
                $ids[] = new PostId($id);
            } catch (DomainError) {
                // Not the id of a post: none on the site.
            }
        }

        $posts = [];

        foreach ($postRepository->findByIds($ids) as $post) {
            $card = PostOnSiteCardResponse::fromPost($post);

            if (null !== $card) {
                $posts[$post->id()->value()] = $card;
            }
        }

        return $posts;
    }
}
