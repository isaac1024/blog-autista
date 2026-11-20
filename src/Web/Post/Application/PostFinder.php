<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostRepository;

/**
 * Finds the post on the site first published on a day, as Y-m-d, with a
 * slug, whatever section its address names. A day that can't be finds none.
 */
final readonly class PostFinder
{
    public function __construct(
        private string $day,
        private string $slug,
    ) {}

    public function __invoke(PostRepository $postRepository): PostResponse
    {
        $address = \sprintf('%s/%s', $this->day, $this->slug);
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->day);

        // A day such as 2026-02-30 rolls over into another; no post has it.
        if (false === $day || $day->format('Y-m-d') !== $this->day) {
            throw PostNotFound::atAddress($address);
        }

        $post = $postRepository->findAt($day, $this->slug) ?? throw PostNotFound::atAddress($address);

        return PostResponse::fromPost($post);
    }
}
