<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * A post of a social network: who wrote it, the account it was written
 * from, the day it was, what it says, and whether it shows photos or videos
 * too. The facade paints them all, so the post can be read without loading
 * it, and still once it is gone.
 */
final readonly class SocialPostDetails extends ResourceDetails
{
    public const int AUTHOR_MAX_LENGTH = 100;

    public const int HANDLE_MAX_LENGTH = 100;

    public const int TEXT_MAX_LENGTH = 3000;

    private ?string $author;

    private ?string $handle;

    private ?string $text;

    public function __construct(
        ?string $author,
        ?string $handle,
        private ?\DateTimeImmutable $postedOn,
        ?string $text,
        private bool $withMedia,
    ) {
        $this->author = DetailText::line('author', $author, self::AUTHOR_MAX_LENGTH);
        $this->handle = DetailText::line('handle', $handle, self::HANDLE_MAX_LENGTH);
        $this->text = DetailText::lines('text', $text, self::TEXT_MAX_LENGTH);
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::SocialPost;
    }

    #[\Override]
    public function assertPublishable(): void
    {
        if (null === $this->author || null === $this->handle) {
            throw InvalidResourcePublication::withoutAuthor();
        }

        if (null === $this->postedOn) {
            throw InvalidResourcePublication::withoutDate();
        }

        if (null === $this->text) {
            throw InvalidResourcePublication::withoutText();
        }
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'author' => $this->author,
            'handle' => $this->handle,
            'posted_on' => $this->postedOn?->format('Y-m-d'),
            'text' => $this->text,
            'with_media' => $this->withMedia,
        ];
    }
}
