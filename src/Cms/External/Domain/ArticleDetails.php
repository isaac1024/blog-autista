<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * An article of the web, such as a piece of news: who publishes it and the
 * day it was published, when they are told.
 */
final readonly class ArticleDetails extends ResourceDetails
{
    public const int PUBLISHER_MAX_LENGTH = 100;

    private ?string $publisher;

    public function __construct(
        ?string $publisher,
        private ?\DateTimeImmutable $publishedOn,
    ) {
        $this->publisher = DetailText::line('publisher', $publisher, self::PUBLISHER_MAX_LENGTH);
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Article;
    }

    #[\Override]
    public function assertPublishable(): void {}

    #[\Override]
    public function toArray(): array
    {
        return ['publisher' => $this->publisher, 'published_on' => $this->publishedOn?->format('Y-m-d')];
    }
}
