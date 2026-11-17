<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Post\Domain\PostSelection;
use Cms\Shared\Infrastructure\Ui\CmsPostCards;
use Cms\Shared\Infrastructure\Ui\PreviewLayout;
use Cms\Taxonomy\Application\TermResponse;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteTermPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Composes the version of a term last saved as the site would serve it once
 * published, in the chrome the site shows now, with the first page of its
 * posts on the site.
 *
 * Search engines never index a preview nor follow its links, whatever the
 * term asks of them: its markup says so, and so does every response that
 * serves it, in the X-Robots-Tag header.
 */
final readonly class PreviewTerm
{
    public const string ROBOTS = 'noindex,nofollow';

    /**
     * As many posts as the first page of a term lists on the site.
     */
    private const int POSTS = 20;

    public function __construct(
        private PreviewLayout $previewLayout,
        private SiteTermPage $siteTermPage,
        private TermAddress $termAddress,
        private UrlGeneratorInterface $urlGenerator,
        private CmsPostCards $postCards,
    ) {}

    public function __invoke(TaxonomySegment $taxonomy, TermResponse $term): Page
    {
        $canonical = null === $term->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->termAddress->address($taxonomy, $term->slug, UrlGeneratorInterface::ABSOLUTE_URL);

        return ($this->siteTermPage)(
            $this->previewLayout->page($term->name)->withSearchEngineMetadata(
                '' === $term->metaDescription ? null : $term->metaDescription,
                self::ROBOTS,
                $canonical,
            ),
            $term->name,
            ($this->postCards)(
                TaxonomySegment::Sections === $taxonomy ? new PostSelection($term->id, null, []) : new PostSelection(null, null, [$term->id]),
                self::POSTS,
            ),
            null,
        );
    }
}
