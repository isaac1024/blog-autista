<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageResponse;
use Cms\Post\Infrastructure\Ui\CmsMosaics;
use Cms\Redirect\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Composes the version of a page last saved as the site would serve it once
 * published, in the chrome the site shows now: only what is on the site is
 * painted, so an image or a linked page off it is left out, as it would be
 * there. Links lead to the site.
 *
 * Search engines never index a preview nor follow its links, whatever the
 * page asks of them: its markup says so, and so does every response that
 * serves it, in the X-Robots-Tag header.
 *
 * It takes one collaborator for each part of the site the page shows.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class PreviewPage
{
    public const string ROBOTS = 'noindex,nofollow';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SitePage $sitePage,
        private PreviewLayout $previewLayout,
        private ImageRepository $imageRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsLinkedContents $linkedContents,
        private CmsMosaics $mosaics,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $content = RichText::fromArray($page->content);
        $images = array_map(
            static fn (ImageOnSiteResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            $this->imagesOnSite($content->imageIds()),
        );

        $canonical = $page->home || null === $page->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        return ($this->sitePage)(
            $this->previewLayout->page($page->title)->withSearchEngineMetadata(
                '' === $page->metaDescription ? null : $page->metaDescription,
                self::ROBOTS,
                $canonical,
            ),
            $page->title,
            $content,
            $images,
            ($this->linkedContents)($content, $images, $page->id),
            ($this->mosaics)($content),
        );
    }

    /**
     * The images on the site among those given, keyed by id; one off it, as
     * the one its address now leads to, as the site paints them.
     *
     * @param list<string> $ids
     *
     * @return array<string, ImageOnSiteResponse>
     */
    private function imagesOnSite(array $ids): array
    {
        return new ReferenceResolver('image', $ids, null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
    }
}
