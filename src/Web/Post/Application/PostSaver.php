<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

/**
 * Puts on the site the version of a post the CMS published, in place of the
 * one there, if any.
 */
final readonly class PostSaver
{
    public function __construct(private CmsPostPublished $event) {}

    public function __invoke(PostRepository $postRepository): void
    {
        $post = $postRepository->find(new PostId($this->event->aggregateId()));

        if (null === $post) {
            $post = Post::publish($this->event);
        } else {
            $post->republish($this->event);
        }

        $post->save($postRepository);
    }
}
