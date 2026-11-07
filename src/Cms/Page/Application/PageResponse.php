<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\Page;

/**
 * Read model of a page handed to the adapters, so they don't touch the aggregate.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PageResponse
{
    /**
     * @param ?string                                                 $slug                  null for the home page, and for any other page left without one
     * @param string                                                  $type                  one of the PageType values
     * @param bool                                                    $home                  whether it is the home page, which has no slug
     * @param bool                                                    $unique                whether it is one of the unique pages, which can't be deleted
     * @param array{version: int, blocks: list<array<string, mixed>>} $content               in the stored rich text format
     * @param string                                                  $metaDescription       empty when there is none
     * @param string                                                  $robots                one of the Robots values
     * @param string                                                  $status                one of the PageStatus values
     * @param bool                                                    $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param ?string                                                 $publishedSlug         the slug it has on the site, null while it isn't there
     * @param bool                                                    $hasSomethingToPublish whether publishing would change the site
     * @param bool                                                    $canBeUnpublished      whether it can be taken off the site
     * @param bool                                                    $canBeDeleted          whether it can be deleted
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public string $type,
        public bool $home,
        public bool $unique,
        public array $content,
        public string $metaDescription,
        public string $robots,
        public string $status,
        public bool $changed,
        public ?string $publishedSlug,
        public \DateTimeImmutable $updatedAt,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->id()->value(),
            $page->title()->value(),
            $page->slug()?->value(),
            $page->type()->value,
            $page->type()->isHome(),
            $page->type()->isUnique(),
            $page->content()->toArray(),
            $page->seo()->description() ?? '',
            $page->seo()->robots()->value,
            $page->status()->value,
            $page->hasUnpublishedChanges(),
            $page->publishedSlug()?->value(),
            $page->timeline()->updatedAt(),
            $page->hasSomethingToPublish(),
            $page->canBeUnpublished(),
            $page->canBeDeleted(),
        );
    }
}
