<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteChrome;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Application\SettingsOnSiteFinder;
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
        private ImageRepository $imageRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteLinkedContents $linkedContents,
    ) {}

    public function page(string $title): Page
    {
        $settings = new SettingsOnSiteFinder()($this->settingsRepository);
        $homeUrl = $this->urlGenerator->generate('web_home');

        if (null === $settings) {
            return WebLayout::page($title, new SiteChrome(null, $homeUrl, [], [], null));
        }

        $targets = $this->linkedContents->menuTargets([...$settings->mainMenu, ...$settings->footerMenu]);

        return WebLayout::page($title, new SiteChrome(
            $settings->name,
            $homeUrl,
            self::links($settings->mainMenu, $targets),
            self::links($settings->footerMenu, $targets),
            $this->shareImage($settings),
        ));
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
            fn (array $ids): array => new ImagesOnSiteFinder($ids)($this->imageRepository),
            $this->redirectsByOrigin,
        )[$settings->defaultImageId] ?? null;

        return null === $image ? null : SiteMedia::shareImage($image->path, $image->alt);
    }
}
