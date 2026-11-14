<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Domain\Slug;
use Shared\Domain\DomainError;

/**
 * Finds the post at an address of the site, by the slug of its section, the
 * day it was first published, as Y-m-d, and its own slug: the one on the
 * site there, or the one off the site whose last address it was. An address
 * that can't be one of a post has none.
 */
final readonly class PostAtAddressFinder
{
    public function __construct(
        private string $section,
        private string $day,
        private string $slug,
    ) {}

    public function onSite(PostRepository $postRepository): ?PostResponse
    {
        $address = $this->address();
        $post = null === $address ? null : $postRepository->findOnSiteAt($address);

        return null === $post ? null : PostResponse::fromPost($post);
    }

    public function lastOffSite(PostRepository $postRepository): ?PostResponse
    {
        $address = $this->address();
        $post = null === $address ? null : $postRepository->findOffSiteByLastAddress($address);

        return null === $post ? null : PostResponse::fromPost($post);
    }

    private function address(): ?PostAddress
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->day, new \DateTimeZone(PostAddress::TIME_ZONE));

        // A day the calendar doesn't have, such as 2026-02-30, names none.
        if (false === $day || $day->format('Y-m-d') !== $this->day) {
            return null;
        }

        try {
            return new PostAddress(new Slug($this->section), $day, new Slug($this->slug));
        } catch (DomainError) {
            return null;
        }
    }
}
