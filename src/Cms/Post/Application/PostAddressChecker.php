<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostAddressAvailability;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;

/**
 * Checks, before publishing, that the address a post would have once
 * published as it is saved isn't another's on the site, so the editor
 * isn't asked about the address it leaves for a publication that can't be.
 */
final readonly class PostAddressChecker
{
    public function __construct(private string $id) {}

    public function __invoke(PostRepository $postRepository, Classifiers $classifiers): void
    {
        $post = $postRepository->find(new PostId($this->id)) ?? throw PostNotFound::withId($this->id);

        new PostAddressAvailability($post, $post->nextAddress($classifiers))($postRepository);
    }
}
