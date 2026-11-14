<?php

declare(strict_types=1);

namespace Cms\Post\Application;

/**
 * A post as the site has it: its title there, and where it is served.
 */
final readonly class PostOnSiteResponse
{
    public function __construct(
        public string $title,
        public PostAddressResponse $address,
    ) {}
}
