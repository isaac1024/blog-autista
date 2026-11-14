<?php

declare(strict_types=1);

namespace Web\Author\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteAuthorPage;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorResponse;
use Web\Image\Application\ImageResponse;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Infrastructure\Controller\SiteLayout;

/**
 * Composes the page of an author of the site, painted as SiteAuthorPage
 * paints it, in the chrome of the site, presenting itself to search engines
 * with its own address as its canonical URL. Its bio paints only the images
 * and the pages on the site, as the content of a page does.
 */
final readonly class PublishedAuthor
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SiteAuthorPage $siteAuthorPage,
        private SiteLayout $siteLayout,
        private ImageRepository $imageRepository,
        private PageRepository $pageRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    public function __invoke(AuthorResponse $author): Page
    {
        $canonical = $this->urlGenerator->generate('web_author', ['slug' => $author->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        // An image off the site is painted as the one its address now leads
        // to, if any, so the pages its caption links to are those of that one.
        $images = array_map(
            static fn (ImageResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            new ReferenceResolver('image', $author->bio->imageIds(), null)(
                fn (array $ids): array => new PublishedImagesFinder($ids)($this->imageRepository),
                $this->redirectsByOrigin,
            ),
        );

        return ($this->siteAuthorPage)(
            $this->siteLayout->page($author->name)
                ->withSearchEngineMetadata($author->metaDescription, $author->robots.',max-image-preview:large', $canonical)
                ->withCountedVisits('author', $author->id),
            $author->name,
            SiteMedia::avatar($author->avatarName, $author->name),
            $author->bio,
            $images,
            $this->linkedPages(SitePage::linkedPageIds($author->bio, $images)),
        );
    }

    /**
     * The published pages among those linked, with their current address,
     * keyed by id; one off the site, as the page its address now leads to.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, array{url: string, title: string}>
     */
    private function linkedPages(array $pageIds): array
    {
        return array_map(fn (PageResponse $target): array => [
            'url' => null === $target->slug
                ? $this->urlGenerator->generate('web_home')
                : $this->urlGenerator->generate('web_page', ['slug' => $target->slug]),
            'title' => $target->title,
        ], new ReferenceResolver('page', $pageIds, null)(
            fn (array $ids): array => new PublishedPagesFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        ));
    }
}
