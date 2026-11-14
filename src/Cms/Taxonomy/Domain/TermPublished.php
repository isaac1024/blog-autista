<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A term went on the site, or its version there was replaced, with all the
 * site needs to serve it.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class TermPublished extends RevisedDomainEvent
{
    public function __construct(
        string $aggregateId,
        private string $taxonomy,
        private string $name,
        private string $slug,
        private string $metaDescription,
        private string $robots,
        private string $firstPublishedAt,
        private string $lastPublishedAt,
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
    public function toPrimitives(): array
    {
        return [
            'taxonomy' => $this->taxonomy,
            'name' => $this->name,
            'slug' => $this->slug,
            'meta_description' => $this->metaDescription,
            'robots' => $this->robots,
            'first_published_at' => $this->firstPublishedAt,
            'last_published_at' => $this->lastPublishedAt,
            'revision' => $this->revision(),
        ];
    }
}
