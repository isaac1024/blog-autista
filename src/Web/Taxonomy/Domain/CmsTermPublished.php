<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;
use Web\Shared\Domain\Robots;

/**
 * The CMS put a term on the site, or replaced its version there, with all the
 * site needs to serve it. The site declares it on its own, by the name the
 * CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsTermPublished extends RevisedReceivedEvent
{
    public function __construct(
        string $aggregateId,
        private Taxonomy $taxonomy,
        private string $name,
        private string $slug,
        private ?string $metaDescription,
        private Robots $robots,
        private \DateTimeImmutable $firstPublishedAt,
        private \DateTimeImmutable $lastPublishedAt,
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
        $metaDescription = $body->string('meta_description');

        return new static(
            $aggregateId,
            Taxonomy::from($body->string('taxonomy')),
            $body->string('name'),
            $body->string('slug'),
            '' === $metaDescription ? null : $metaDescription,
            Robots::from($body->string('robots')),
            new \DateTimeImmutable($body->string('first_published_at')),
            new \DateTimeImmutable($body->string('last_published_at')),
            $body->integer('revision'),
        );
    }

    public function taxonomy(): Taxonomy
    {
        return $this->taxonomy;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function robots(): Robots
    {
        return $this->robots;
    }

    public function firstPublishedAt(): \DateTimeImmutable
    {
        return $this->firstPublishedAt;
    }

    public function lastPublishedAt(): \DateTimeImmutable
    {
        return $this->lastPublishedAt;
    }
}
