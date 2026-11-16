<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS put a post on the site, or replaced its version there: what the
 * sitemap needs of it is where it is, under the slug of its section on the
 * day it was first published, whether search engines may index it, when it
 * was last published, the image it opens with, and its section and tags.
 * The sitemap declares it on its own, by the name the CMS publishes it under.
 *
 * It carries what the sitemap needs, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsPostPublished extends RevisedReceivedEvent
{
    /**
     * @param string       $day    the day it was first published, as Y-m-d
     * @param list<string> $tagIds
     */
    public function __construct(
        string $aggregateId,
        public string $slug,
        public string $sectionId,
        public string $sectionSlug,
        public array $tagIds,
        public string $openingImageId,
        public string $robots,
        public string $day,
        public \DateTimeImmutable $lastPublishedAt,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.post.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('slug'),
            $body->string('section_id'),
            $body->string('section_slug'),
            $body->stringList('tag_ids'),
            $body->string('opening_image_id'),
            $body->string('robots'),
            $body->string('day'),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }
}
