<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Shared\Domain\RichText\RichText;
use Web\Page\Domain\Page;

/**
 * Read model of a page on the site handed to the adapters, so they don't
 * touch the aggregate.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PageResponse
{
    /**
     * @param ?string $slug            null for the home page
     * @param ?string $metaDescription null when there is none
     * @param string  $robots          one of the Robots values
     * @param string  $type            one of the PageType values, such as the one of the page that searches the posts
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $slug,
        public RichText $content,
        public ?string $metaDescription,
        public string $robots,
        public string $type,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->id()->value(),
            $page->title(),
            $page->slug(),
            $page->content(),
            $page->metaDescription(),
            $page->robots()->value,
            $page->type()->value,
        );
    }
}
