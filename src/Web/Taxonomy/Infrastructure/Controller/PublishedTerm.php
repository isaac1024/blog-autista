<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteTermPage;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Application\TermResponse;

/**
 * Composes the page of a term of the site, painted as SiteTermPage paints
 * it, in the chrome of the site, presenting itself to search engines with
 * its own address as its canonical URL.
 */
final readonly class PublishedTerm
{
    public function __construct(
        private SiteLayout $siteLayout,
        private SiteTermPage $siteTermPage,
    ) {}

    public function __invoke(TermResponse $term, string $canonical): Page
    {
        return ($this->siteTermPage)(
            $this->siteLayout->page($term->name)
                ->withSearchEngineMetadata($term->metaDescription, $term->robots, $canonical)
                ->withCountedVisits('term', $term->id),
            $term->name,
        );
    }
}
