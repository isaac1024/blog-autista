<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostUsage;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Deletes an existing post, failing when it doesn't exist.
 */
final readonly class PostDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository, EventBus $eventBus, Transaction $transaction, PostUsage $postUsage): void
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        $post->delete($postRepository, $eventBus, $transaction, $postUsage);
    }
}
