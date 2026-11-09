<?php

declare(strict_types=1);

namespace Web\Page\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;

/**
 * Composes a page of the site, painted as SitePage paints it, in the chrome of
 * the site, presenting itself to search engines with its own address as its
 * canonical URL.
 */
final readonly class PublishedPage
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SitePage $sitePage,
        private ImageRepository $imageRepository,
        private PageRepository $pageRepository,
        private SiteLayout $siteLayout,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $canonical = null === $page->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        // An image off the site is painted as the one its address now leads
        // to, if any, so the pages its caption links to are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $page->content->imageIds(), null)(
                fn (array $ids): array => new PublishedImagesFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        // Search engines may show its images large, in results and Discover.
        return ($this->sitePage)(
            $this->siteLayout->page($page->title)
                ->withSearchEngineMetadata($page->metaDescription, $page->robots.',max-image-preview:large', $canonical),
            $page->title,
            $page->content,
            $images,
            $this->linkedPages(SitePage::linkedPageIds($page->content, $images), $page->id),
        );
    }

    /**
     * The published pages among those linked, with their current address,
     * keyed by id; one off the site, as the page its address now leads to.
     * A target not on the site, nor redirected, isn't here, so its text
     * stays unlinked and its widget isn't painted; nor is one redirected to
     * the page painted, which doesn't link itself.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function linkedPages(array $pageIds, string $pageId): array
    {
        return array_map(fn (PageResponse $target): array => [
            'url' => null === $target->slug
                ? $this->urlGenerator->generate('web_home')
                : $this->urlGenerator->generate('web_page', ['slug' => $target->slug]),
            'title' => $target->title,
        ], new ReferenceResolver('page', $pageIds, $pageId)(
            fn (array $ids): array => new PublishedPagesFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        ));
    }
}
