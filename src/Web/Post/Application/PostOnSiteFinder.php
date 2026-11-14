<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

/**
 * Finds a post on the site by its id; none while it is off it.
 */
final readonly class PostOnSiteFinder
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository): ?PostResponse
    {
        $post = $postRepository->find(new PostId($this->id));

        return null === $post ? null : PostResponse::fromPost($post);
    }
}
