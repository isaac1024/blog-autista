<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRedirects;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Takes a published post off the site.
 */
final readonly class PostUnpublisher
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository, EventBus $eventBus, Transaction $transaction, PostRedirects $postRedirects): void
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        $post->unpublish($postRedirects);
        $post->save($postRepository, $eventBus, $transaction);
    }
}
