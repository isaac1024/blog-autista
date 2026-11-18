<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Throws away what was edited on a published post since it was published,
 * back to the version on the site.
 */
final readonly class PostChangesDiscarder
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository, EventBus $eventBus, Transaction $transaction): void
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        $post->discardChanges();
        $post->save($postRepository, $eventBus, $transaction);
    }
}
