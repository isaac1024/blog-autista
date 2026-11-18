<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRepository;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;

/**
 * Creates a post from the submitted title, deriving its slug from it.
 */
final readonly class PostCreator
{
    public function __construct(
        private string $id,
        private string $title,
    ) {}

    public function __invoke(PostRepository $postRepository, EventBus $eventBus, Transaction $transaction): void
    {
        Post::create($this->id, $this->title)->save($postRepository, $eventBus, $transaction);
    }
}
