<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;

/**
 * Finds a post by its id, failing when it doesn't exist.
 */
final readonly class PostFinder
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository): PostResponse
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        return PostResponse::fromPost($post);
    }
}
