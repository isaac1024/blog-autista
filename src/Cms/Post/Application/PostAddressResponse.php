<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostAddress;

/**
 * Where the site serves a post, or would: the slug of its section, the day
 * it was first published, as Y-m-d, and its own slug.
 */
final readonly class PostAddressResponse
{
    public function __construct(
        public string $section,
        public string $day,
        public string $slug,
    ) {}

    public static function fromAddress(PostAddress $address): self
    {
        return new self($address->section()->value(), $address->day(), $address->slug()->value());
    }
}
