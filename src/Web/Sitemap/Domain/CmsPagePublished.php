<?php

declare(strict_types=1);

namespace Web\Sitemap\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS put a page on the site, or replaced its version there: what the
 * sitemap needs of it is where it is, whether search engines may index it,
 * and when it was last published. The sitemap declares it on its own, by
 * the name the CMS publishes it under.
 */
final readonly class CmsPagePublished extends RevisedReceivedEvent
{
    /**
     * @param ?string $slug null for the home page
     */
    public function __construct(
        string $aggregateId,
        public ?string $slug,
        public string $robots,
        public \DateTimeImmutable $lastPublishedAt,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.page.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->nullableString('slug'),
            $body->string('robots'),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }
}
