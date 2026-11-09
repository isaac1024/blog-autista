<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteChrome;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\PublishedImagesFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Application\PublishedPagesFinder;
use Web\Page\Domain\PageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Application\PublishedSettingsFinder;
use Web\Settings\Application\SettingsResponse;
use Web\Settings\Domain\SettingsRepository;

/**
 * Starts every page of the site in its layout, with the chrome its settings
 * give it: the name of the blog, the menus and the default image.
 *
 * Only what is on the site is painted: a menu leaves out its pages off the
 * site, and an image off it is never shared, unless the address of either
 * now leads to one on it, which is painted instead. Until the settings are
 * published, pages go without chrome.
 */
final readonly class SiteLayout
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SettingsRepository $settingsRepository,
        private PageRepository $pageRepository,
        private ImageRepository $imageRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
    ) {}

    public function page(string $title): Page
    {
        $settings = new PublishedSettingsFinder()($this->settingsRepository);
        $homeUrl = $this->urlGenerator->generate('web_home');

        if (null === $settings) {
            return WebLayout::page($title, new SiteChrome(null, $homeUrl, [], [], null));
        }

        $pages = $this->menuPages($settings);

        return WebLayout::page($title, new SiteChrome(
            $settings->name,
            $homeUrl,
            $this->links($settings->mainMenu, $pages),
            $this->links($settings->footerMenu, $pages),
            $this->shareImage($settings),
        ));
    }

    /**
     * The pages of the menus on the site, keyed by id; one off it, as the
     * one its address now leads to.
     *
     * @return array<string, PageResponse>
     */
    private function menuPages(SettingsResponse $settings): array
    {
        return new ReferenceResolver('page', [
            ...array_column($settings->mainMenu, 'page_id'),
            ...array_column($settings->footerMenu, 'page_id'),
        ], null)(
            fn (array $ids): array => new PublishedPagesFinder($ids)($this->pageRepository),
            $this->redirectsByOrigin,
        );
    }

    /**
     * The entries of a menu whose pages are on the site, named by their
     * labels or else by the titles of their pages.
     *
     * @param list<array{page_id: string, label: ?string}> $menu
     * @param array<string, PageResponse>                  $pages on the site, keyed by id
     *
     * @return list<array{label: string, url: string}>
     */
    private function links(array $menu, array $pages): array
    {
        $links = [];

        foreach ($menu as $item) {
            $page = $pages[$item['page_id']] ?? null;

            if (null === $page) {
                continue;
            }

            $links[] = [
                'label' => $item['label'] ?? $page->title,
                'url' => null === $page->slug
                    ? $this->urlGenerator->generate('web_home')
                    : $this->urlGenerator->generate('web_page', ['slug' => $page->slug]),
            ];
        }

        return $links;
    }

    /**
     * The largest copy of the default image, while it is on the site.
     *
     * @return ?array{src: string, width: int, height: int, alt: string}
     */
    private function shareImage(SettingsResponse $settings): ?array
    {
        if (null === $settings->defaultImageId) {
            return null;
        }

        $image = new ReferenceResolver('image', [$settings->defaultImageId], null)(
            fn (array $ids): array => new PublishedImagesFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        )[$settings->defaultImageId] ?? null;

        return null === $image ? null : SiteMedia::shareImage($image->path, $image->alt);
    }
}
