<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Application\SettingsOnSiteFinder;
use Cms\Settings\Application\SettingsOnSiteResponse;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteChrome;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Shared\Infrastructure\Ui\WebLayout;
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
 */
final readonly class PreviewPage
{
    public const string ROBOTS = 'noindex,nofollow';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SitePage $sitePage,
        private SettingsRepository $settingsRepository,
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
    ) {}

    public function __invoke(PageResponse $page): Page
    {
        $content = RichText::fromArray($page->content);
        $images = array_map(
            static fn (ImageOnSiteResponse $image): array => SiteMedia::image($image->path, $image->alt, $image->caption),
            $this->images($content->imageIds()),
        );

        $canonical = $page->home || null === $page->slug
            ? $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug], UrlGeneratorInterface::ABSOLUTE_URL);

        return ($this->sitePage)(
            WebLayout::page($page->title, $this->chrome())->withSearchEngineMetadata(
                '' === $page->metaDescription ? null : $page->metaDescription,
                self::ROBOTS,
                $canonical,
            ),
            $page->title,
            $content,
            $images,
            array_map(
                fn (PageOnSiteResponse $target): array => ['url' => $this->url($target), 'title' => $target->title],
                $this->pages(SitePage::linkedPageIds($content, $images), $page->id),
            ),
        );
    }

    /**
     * The chrome the site paints from its published settings, without any
     * until they are published.
     */
    private function chrome(): SiteChrome
    {
        $homeUrl = $this->urlGenerator->generate('web_home');
        $settings = new SettingsOnSiteFinder()($this->settingsRepository);

        if (null === $settings) {
            return new SiteChrome(null, $homeUrl, [], [], null);
        }

        $pages = $this->pages([
            ...array_column($settings->mainMenu, 'page_id'),
            ...array_column($settings->footerMenu, 'page_id'),
        ], null);

        return new SiteChrome(
            $settings->name,
            $homeUrl,
            $this->links($settings->mainMenu, $pages),
            $this->links($settings->footerMenu, $pages),
            $this->shareImage($settings),
        );
    }

    /**
     * The entries of a menu whose pages are on the site, named by their
     * labels or else by the titles of their pages.
     *
     * @param list<array{page_id: string, label: ?string}> $menu
     * @param array<string, PageOnSiteResponse>            $pages on the site, keyed by id
     *
     * @return list<array{label: string, url: string}>
     */
    private function links(array $menu, array $pages): array
    {
        $links = [];

        foreach ($menu as $item) {
            $page = $pages[$item['page_id']] ?? null;

            if (null !== $page) {
                $links[] = ['label' => $item['label'] ?? $page->title, 'url' => $this->url($page)];
            }
        }

        return $links;
    }

    /**
     * @return ?array{src: string, width: int, height: int, alt: string}
     */
    private function shareImage(SettingsOnSiteResponse $settings): ?array
    {
        if (null === $settings->defaultImage) {
            return null;
        }

        $image = $this->images([$settings->defaultImage])[$settings->defaultImage] ?? null;

        return null === $image ? null : SiteMedia::shareImage($image->path, $image->alt);
    }

    /**
     * The images on the site among those given, keyed by id; one off it, as
     * the one its address now leads to, as the site paints them.
     *
     * @param list<string> $ids
     *
     * @return array<string, ImageOnSiteResponse>
     */
    private function images(array $ids): array
    {
        return new ReferenceResolver('image', $ids, null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
    }

    /**
     * The pages on the site among those given, keyed by id; one off it, as
     * the one its address now leads to, unless that is the page previewed.
     *
     * @param list<string> $ids
     *
     * @return array<string, PageOnSiteResponse>
     */
    private function pages(array $ids, ?string $except): array
    {
        return new ReferenceResolver('page', $ids, $except)(
            fn (array $ids): array => new PagesOnSiteFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        );
    }

    /**
     * Where the site serves a page on it.
     */
    private function url(PageOnSiteResponse $page): string
    {
        return null === $page->slug
            ? $this->urlGenerator->generate('web_home')
            : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]);
    }
}
