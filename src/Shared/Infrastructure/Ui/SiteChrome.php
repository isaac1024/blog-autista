<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

/**
 * What the layout of the public site paints around every page: the name of
 * the blog, the links of its main and footer menus, and the image a page is
 * shared with when it has none of its own. Each may be missing, and the
 * layout leaves out what is.
 */
final readonly class SiteChrome
{
    /**
     * @param ?string                                                   $brand       null while the site has no name
     * @param list<array{label: string, url: string}>                   $mainLinks
     * @param list<array{label: string, url: string}>                   $footerLinks
     * @param ?array{src: string, width: int, height: int, alt: string} $shareImage  null for none
     */
    public function __construct(
        public ?string $brand,
        public string $homeUrl,
        public array $mainLinks,
        public array $footerLinks,
        public ?array $shareImage,
    ) {}
}
