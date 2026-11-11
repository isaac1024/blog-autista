<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * A page went on the site, or its version there was replaced, with all the
 * site needs to serve it.
 *
 * It carries what the site shows, one parameter each. The home page has no
 * slug.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PagePublished extends RevisedDomainEvent
{
    /**
     * @param array{version: int, blocks: list<array<string, mixed>>} $content in the stored rich text format
     */
    public function __construct(
        string $aggregateId,
        private string $type,
        private string $title,
        private ?string $slug,
        private array $content,
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
        return 'cms.page.published';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'meta_description' => $this->metaDescription,
            'robots' => $this->robots,
            'first_published_at' => $this->firstPublishedAt,
            'last_published_at' => $this->lastPublishedAt,
            'revision' => $this->revision(),
        ];
    }
}
