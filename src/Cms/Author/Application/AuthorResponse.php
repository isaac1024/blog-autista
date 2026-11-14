<?php

declare(strict_types=1);

namespace Cms\Author\Application;

use Cms\Author\Domain\Author;

/**
 * Read model of an author handed to the adapters, so they don't touch the
 * aggregate.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class AuthorResponse
{
    /**
     * @param ?string                                                 $slug                  null while it is left without one
     * @param ?string                                                 $avatar                where the avatar being edited is kept, relative to the avatar storage; null until one is uploaded
     * @param array{version: int, blocks: list<array<string, mixed>>} $bio                   in the stored rich text format
     * @param string                                                  $metaDescription       empty when there is none
     * @param string                                                  $robots                one of the Robots values
     * @param string                                                  $status                one of the AuthorStatus values
     * @param bool                                                    $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param ?string                                                 $publishedSlug         the slug it has on the site, null while it isn't there
     * @param bool                                                    $hasSomethingToPublish whether publishing would change the site
     * @param bool                                                    $canBeUnpublished      whether it can be taken off the site
     * @param bool                                                    $canBeDeleted          whether it can be deleted
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug,
        public ?string $avatar,
        public array $bio,
        public string $metaDescription,
        public string $robots,
        public string $status,
        public bool $changed,
        public ?string $publishedSlug,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
    ) {}

    public static function fromAuthor(Author $author): self
    {
        return new self(
            $author->id()->value(),
            $author->name()->value(),
            $author->slug()?->value(),
            $author->avatar()?->value(),
            $author->bio()->toArray(),
            $author->seo()->description() ?? '',
            $author->seo()->robots()->value,
            $author->status()->value,
            $author->hasUnpublishedChanges(),
            $author->publishedSlug()?->value(),
            $author->timeline()->createdAt(),
            $author->timeline()->updatedAt(),
            $author->hasSomethingToPublish(),
            $author->canBeUnpublished(),
            $author->canBeDeleted(),
        );
    }
}
