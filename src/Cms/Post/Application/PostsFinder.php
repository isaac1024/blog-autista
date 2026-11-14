<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\DomainError;

/**
 * Finds the posts among those given, in the order given; an id of no post
 * is left out.
 */
final readonly class PostsFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return list<PostResponse>
     */
    public function __invoke(PostRepository $postRepository): array
    {
        $posts = [];

        foreach (array_unique($this->ids) as $id) {
            try {
                $post = $postRepository->find(new PostId($id));
            } catch (DomainError) {
                // Not the id of a post: none to find.
                continue;
            }

            if (null !== $post) {
                $posts[] = PostResponse::fromPost($post);
            }
        }

        return $posts;
    }
}
