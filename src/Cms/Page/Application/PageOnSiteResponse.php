<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\PageVersion;

/**
 * A page as the site serves it: the title and address it was published with,
 * whatever is being edited since.
 */
final readonly class PageOnSiteResponse
{
    /**
     * @param ?string $slug null for the home page
     */
    public function __construct(
        public string $title,
        public ?string $slug,
    ) {}

    public static function fromVersion(PageVersion $version): self
    {
        return new self($version->title()->value(), $version->slug()?->value());
    }
}
