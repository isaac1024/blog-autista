<?php

declare(strict_types=1);

namespace Cms\Post\Application;

/**
 * A post as the site has it: its title there, and where it is served; what
 * it says of itself to search engines, the section it is filed under and
 * the image it opens with, as a card of it shows them; and when it was
 * first published.
 */
final readonly class PostOnSiteResponse
{
    public function __construct(
        public string $title,
        public PostAddressResponse $address,
        public ?string $excerpt,
        public string $sectionId,
        public ?string $openingImageId,
        public \DateTimeImmutable $firstPublishedAt,
    ) {}
}
