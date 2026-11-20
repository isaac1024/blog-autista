<?php

declare(strict_types=1);

namespace Web\Post\Domain;

/**
 * What a list of posts shows of one, a card or an entry of the feed, read
 * without its content: its title, its address, the section it is under and
 * who signs it, the image it opens with, its summary and when it was
 * published.
 *
 * It carries what a card shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostCard
{
    /**
     * @param \DateTimeImmutable $day the day it was first published, served in its address
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $sectionId,
        public string $sectionName,
        public string $sectionSlug,
        public string $authorId,
        public string $authorName,
        public string $openingImageId,
        public ?string $metaDescription,
        public \DateTimeImmutable $day,
        public \DateTimeImmutable $firstPublishedAt,
        public \DateTimeImmutable $lastPublishedAt,
    ) {}
}
