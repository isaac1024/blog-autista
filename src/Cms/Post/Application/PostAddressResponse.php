<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\PostAddress;

/**
 * What tells a post on the site apart, or would: the day it was first
 * published, as Y-m-d, and its own slug.
 */
final readonly class PostAddressResponse
{
    public function __construct(
        public string $day,
        public string $slug,
    ) {}

    public static function fromAddress(PostAddress $address): self
    {
        return new self($address->day(), $address->slug()->value());
    }
}
