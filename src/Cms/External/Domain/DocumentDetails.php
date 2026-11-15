<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * A document, a PDF: who publishes it, how heavy it is and how many pages it
 * has, when they are known, so readers know what they open.
 */
final readonly class DocumentDetails extends ResourceDetails
{
    public const int PUBLISHER_MAX_LENGTH = 100;

    private ?string $publisher;

    public function __construct(
        ?string $publisher,
        private ?int $bytes,
        private ?int $pages,
    ) {
        $this->publisher = DetailText::line('publisher', $publisher, self::PUBLISHER_MAX_LENGTH);
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Document;
    }

    #[\Override]
    public function assertPublishable(): void {}

    #[\Override]
    public function toArray(): array
    {
        return ['publisher' => $this->publisher, 'bytes' => $this->bytes, 'pages' => $this->pages];
    }
}
