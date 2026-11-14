<?php

declare(strict_types=1);

namespace Cms\Post\Application;

use Cms\Post\Domain\Post;

/**
 * Read model of a post handed to the adapters, so they don't touch the
 * aggregate.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PostResponse
{
    /**
     * @param ?string                                                 $slug                  null while it is left without one
     * @param ?string                                                 $sectionId             null while it is filed under none
     * @param ?string                                                 $authorId              null while it is signed by none
     * @param list<string>                                            $tagIds                in order
     * @param array{version: int, blocks: list<array<string, mixed>>} $content               in the stored rich text format
     * @param string                                                  $metaDescription       empty when there is none
     * @param string                                                  $robots                one of the Robots values
     * @param string                                                  $status                one of the PostStatus values
     * @param bool                                                    $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param ?string                                                 $publishedSectionSlug  the slug of the section it is served under, null while it isn't on the site
     * @param ?string                                                 $publishedDay          the day it was first published, as Y-m-d, null while it isn't on the site
     * @param ?string                                                 $publishedSlug         the slug it has on the site, null while it isn't there
     * @param bool                                                    $hasSomethingToPublish whether publishing would change the site
     * @param bool                                                    $canBeUnpublished      whether it can be taken off the site
     * @param bool                                                    $canBeDeleted          whether it can be deleted
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public ?string $sectionId,
        public ?string $authorId,
        public array $tagIds,
        public array $content,
        public string $metaDescription,
        public string $robots,
        public string $status,
        public bool $changed,
        public ?string $publishedSectionSlug,
        public ?string $publishedDay,
        public ?string $publishedSlug,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $firstPublishedAt,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
    ) {}

    public static function fromPost(Post $post): self
    {
        $classification = $post->classification();
        $address = $post->publishedAddress();

        return new self(
            $post->id()->value(),
            $post->title()->value(),
            $post->slug()?->value(),
            $classification->section()?->value(),
            $classification->author()?->value(),
            $classification->tags()->values(),
            $post->content()->toArray(),
            $post->seo()->description() ?? '',
            $post->seo()->robots()->value,
            $post->status()->value,
            $post->hasUnpublishedChanges(),
            $address?->section()->value(),
            $address?->day(),
            $address?->slug()->value(),
            $post->timeline()->createdAt(),
            $post->timeline()->updatedAt(),
            $post->timeline()->firstPublishedAt(),
            $post->hasSomethingToPublish(),
            $post->canBeUnpublished(),
            $post->canBeDeleted(),
        );
    }
}
