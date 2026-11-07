<?php

declare(strict_types=1);

namespace Web\Page\Application;

use Shared\Domain\RichText\RichText;
use Web\Page\Domain\Page;

/**
 * Read model of a page on the site handed to the adapters, so they don't
 * touch the aggregate.
 */
final readonly class PageResponse
{
    /**
     * @param string  $slug            empty for the home page
     * @param ?string $metaDescription null when there is none
     * @param string  $robots          one of the Robots values
     */
    public function __construct(
        public string $title,
        public string $slug,
        public RichText $content,
        public ?string $metaDescription,
        public string $robots,
    ) {}

    public static function fromPage(Page $page): self
    {
        return new self(
            $page->title(),
            $page->slug(),
            $page->content(),
            $page->metaDescription(),
            $page->robots()->value,
        );
    }
}
