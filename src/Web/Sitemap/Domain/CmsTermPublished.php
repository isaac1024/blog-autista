<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS put a section or a tag on the site, or replaced its version there:
 * what the sitemap needs of it is its taxonomy, its slug, whether search
 * engines may index it, and when it was last published. The sitemap
 * declares it on its own, by the name the CMS publishes it under.
 */
final readonly class CmsTermPublished extends RevisedReceivedEvent
{
    public function __construct(
        string $aggregateId,
        public string $taxonomy,
        public string $slug,
        public string $robots,
        public \DateTimeImmutable $lastPublishedAt,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.term.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('taxonomy'),
            $body->string('slug'),
            $body->string('robots'),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }
}
