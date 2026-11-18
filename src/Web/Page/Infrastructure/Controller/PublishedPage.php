<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\PageType;
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Shared\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Shared\Infrastructure\Ui\SiteLayout;
use Web\Shared\Infrastructure\Ui\SiteMosaics;
use Web\Shared\Infrastructure\Ui\SiteRobots;

/**
 * Composes a page of the site, painted as SitePage paints it, in the chrome of
 * the site, presenting itself to search engines with its own address as its
 * canonical URL.
 *
 * It takes one collaborator for each part of the site the page shows.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PublishedPage
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SitePage $sitePage,
        private ImageRepository $imageRepository,
        private SiteLayout $siteLayout,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteLinkedContents $linkedContents,
        private SiteMosaics $mosaics,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $canonical = null === $page->slug ? $home : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        // An image off the site is painted as the one its address now leads
        // to, if any, so the pages its caption links to are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $page->content->imageIds(), null)(
                fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        // Its visits are counted; those of a preview or an error never are.
        // Every page but the home one is a step away from it.
        $layout = $this->siteLayout->page($page->title)
            ->withSearchEngineMetadata($page->metaDescription, SiteRobots::meta($page->robots), $canonical)
            ->withCountedVisits('page', $page->id)
            ->withStructuredData(StructuredData::webPage(
                match (PageType::from($page->type)) {
                    PageType::Contact => 'ContactPage',
                    PageType::Search => 'SearchResultsPage',
                    default => 'WebPage',
                },
                $home,
                $canonical,
                $page->title,
                $page->metaDescription,
                null === $page->slug ? [] : StructuredData::breadcrumbOf($canonical),
            ));

        return ($this->sitePage)(
            null === $page->slug ? $layout : $layout->withStructuredData(StructuredData::breadcrumbs($home, $canonical, [['name' => $page->title, 'url' => null]])),
            $page->title,
            $page->content,
            $images,
            ($this->linkedContents)($page->content, $images, $page->id),
            ($this->mosaics)($page->content),
        );
    }
}
