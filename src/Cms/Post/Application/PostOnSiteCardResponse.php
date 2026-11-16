<?php

declare(strict_types=1);

namespace Cms\Post\Application;

/**
 * What a card of a post on the site shows, as a preview paints it: the
 * version on the site, where the site serves it, and when it was first
 * published, which lists order it by.
 *
 * It carries what the card shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostOnSiteCardResponse
{
    /**
     * @param string $day the day it was first published, as Y-m-d
     */
    public function __construct(
        public string $title,
        public ?string $excerpt,
        public string $sectionId,
        public string $sectionSlug,
        public string $day,
        public string $slug,
        public ?string $openingImageId,
        public \DateTimeImmutable $firstPublishedAt,
    ) {}
}
