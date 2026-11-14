<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageOnSiteResponse;
use Cms\Page\Application\PagesOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Application\SettingsOnSiteFinder;
use Cms\Settings\Application\SettingsOnSiteResponse;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteChrome;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Starts every preview of the CMS in the layout of the site, with the chrome
 * the site shows now, from the settings published: the name of the blog, the
 * menus and the default image. Only what is on the site is painted, as the
 * site paints it; until the settings are published, previews go without
 * chrome.
 *
 * It reaches the settings, the pages and the images, as the chrome does.
 */
final readonly class PreviewLayout
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SettingsRepository $settingsRepository,
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
    ) {}

    public function page(string $title): Page
    {
        return WebLayout::page($title, $this->chrome());
    }

    private function chrome(): SiteChrome
    {
        $homeUrl = $this->urlGenerator->generate('web_home');
        $settings = new SettingsOnSiteFinder()($this->settingsRepository);

        if (null === $settings) {
            return new SiteChrome(null, $homeUrl, [], [], null);
        }

        $pages = new ReferenceResolver('page', [
            ...array_column($settings->mainMenu, 'page_id'),
            ...array_column($settings->footerMenu, 'page_id'),
        ], null)(
            fn (array $ids): array => new PagesOnSiteFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        );

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
                $links[] = [
                    'label' => $item['label'] ?? $page->title,
                    'url' => null === $page->slug
                        ? $this->urlGenerator->generate('web_home')
                        : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
                ];
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

        /** @var array<string, ImageOnSiteResponse> $images */
        $images = new ReferenceResolver('image', [$settings->defaultImage], null)(
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        );
        $image = $images[$settings->defaultImage] ?? null;

        return null === $image ? null : SiteMedia::shareImage($image->path, $image->alt);
    }
}
