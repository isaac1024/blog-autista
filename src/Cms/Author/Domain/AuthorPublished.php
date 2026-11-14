<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * An author went on the site, or the version there was replaced, with all
 * the site needs to serve the author's page.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class AuthorPublished extends RevisedDomainEvent
{
    /**
     * @param string                                                  $avatar where the file of the avatar is kept, relative to the avatar storage
     * @param array{version: int, blocks: list<array<string, mixed>>} $bio    in the stored rich text format
     */
    public function __construct(
        string $aggregateId,
        private string $name,
        private string $slug,
        private string $avatar,
        private array $bio,
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
        return 'cms.author.published';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'meta_description' => $this->metaDescription,
            'robots' => $this->robots,
            'first_published_at' => $this->firstPublishedAt,
            'last_published_at' => $this->lastPublishedAt,
            'revision' => $this->revision(),
        ];
    }
}
