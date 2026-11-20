<?php

declare(strict_types=1);

namespace Web\Post\Application;

use Shared\Domain\RichText\RichText;
use Web\Post\Domain\Post;

/**
 * Read model of a post on the site handed to the adapters.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostResponse
{
    /**
     * @param list<string> $tagIds
     * @param string       $day            the day it was first published, as Y-m-d
     * @param int          $readingMinutes the minutes it takes to read
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $sectionId,
        public string $sectionName,
        public string $authorId,
        public string $authorName,
        public array $tagIds,
        public string $openingImageId,
        public RichText $content,
        public ?string $metaDescription,
        public int $readingMinutes,
        public string $robots,
        public string $day,
        public \DateTimeImmutable $firstPublishedAt,
        public \DateTimeImmutable $lastPublishedAt,
    ) {}

    public static function fromPost(Post $post): self
    {
        return new self(
            $post->id()->value(),
            $post->title(),
            $post->slug(),
            $post->sectionId(),
            $post->sectionName(),
            $post->authorId(),
            $post->authorName(),
            $post->tagIds(),
            $post->openingImageId(),
            $post->content(),
            $post->metaDescription(),
            $post->readingMinutes(),
            $post->robots()->value,
            $post->day()->format('Y-m-d'),
            $post->firstPublishedAt(),
            $post->lastPublishedAt(),
        );
    }
}
