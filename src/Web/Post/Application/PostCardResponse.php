<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Web\Post\Domain\PostCard;

/**
 * Read model of a post as a list shows it, a card or an entry of the feed,
 * handed to the adapters: no content, which no list paints.
 *
 * It carries what a card shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostCardResponse
{
    /**
     * @param string $day the day it was first published, as Y-m-d
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $sectionId,
        public string $sectionName,
        public string $sectionSlug,
        public string $authorName,
        public string $openingImageId,
        public ?string $metaDescription,
        public string $day,
        public \DateTimeImmutable $firstPublishedAt,
        public \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function fromCard(PostCard $card): self
    {
        return new self(
            $card->title,
            $card->slug,
            $card->sectionId,
            $card->sectionName,
            $card->sectionSlug,
            $card->authorName,
            $card->openingImageId,
            $card->metaDescription,
            $card->day->format('Y-m-d'),
            $card->firstPublishedAt,
            $card->lastPublishedAt,
        );
    }
}
