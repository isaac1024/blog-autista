<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\CmsPostUnpublished;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

/**
 * Takes off the site a post the CMS unpublished. A post already gone stays
 * gone, so the same event can be taken twice.
 */
final readonly class PostRemover
{
    public function __construct(private CmsPostUnpublished $event) {}

    public function __invoke(PostRepository $postRepository): void
    {
        $postRepository->find(new PostId($this->event->aggregateId()))?->unpublish($postRepository);
    }
}
