<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Image\Application\ImageOnSiteResponse;
use Cms\Image\Application\ImagesOnSiteFinder;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\SearchPageOnSiteFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Infrastructure\Resolution\CmsLinkedContents;
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
 * It reaches the settings, what the menus lead to and the images, as the
 * chrome does.
 */
final readonly class PreviewLayout
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SettingsRepository $settingsRepository,
        private ImageRepository $imageRepository,
        private CmsRedirectsByOrigin $redirectsByOrigin,
        private CmsLinkedContents $linkedContents,
        private PageRepository $pageRepository,
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
            return new SiteChrome(null, $homeUrl, [], [], $this->searchUrl(), null);
        }

        $targets = $this->linkedContents->menuTargets([...$settings->mainMenu, ...$settings->footerMenu]);

        return new SiteChrome(
            $settings->name,
            $homeUrl,
            self::links($settings->mainMenu, $targets),
            self::links($settings->footerMenu, $targets),
            $this->searchUrl(),
            $this->shareImage($settings),
        );
    }

    /**
     * The page that searches the site, as it is on the site, while it is.
     */
    private function searchUrl(): ?string
    {
        $slug = new SearchPageOnSiteFinder()($this->pageRepository)?->slug;

        return null === $slug ? null : $this->urlGenerator->generate('web_page', ['slug' => $slug]);
    }

    /**
     * The entries of a menu whose contents are on the site, named by their
     * labels or else by the names of what they lead to.
     *
     * @param list<array{type: string, id: string, label: ?string}> $menu
     * @param array<string, array{url: string, title: string}>      $targets on the site, keyed by id
     *
     * @return list<array{label: string, url: string}>
     */
    private static function links(array $menu, array $targets): array
    {
        $links = [];

        foreach ($menu as $item) {
            $target = $targets[$item['id']] ?? null;

            if (null !== $target) {
                $links[] = ['label' => $item['label'] ?? $target['title'], 'url' => $target['url']];
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
