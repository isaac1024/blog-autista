<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Domain\Redirect\ReferenceResolver;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteChrome;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\StructuredData;
use Shared\Infrastructure\Ui\WebLayout;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Infrastructure\Ui\AuthorPerson;
use Web\Image\Application\ImagesOnSiteFinder;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\SearchPageFinder;
use Web\Page\Domain\PageRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Application\SettingsOnSiteFinder;
use Web\Settings\Application\SettingsResponse;
use Web\Settings\Domain\SettingsRepository;

/**
 * Starts every page of the site in its layout, with the chrome its settings
 * give it: the name of the blog, the menus and the default image, and a
 * link to the page that searches it while it is on the site.
 *
 * Only what is on the site is painted: a menu leaves out its pages off the
 * site, and an image off it is never shared, unless the address of either
 * now leads to one on it, which is painted instead. Until the settings are
 * published, pages go without chrome.
 *
 * Every page tells search engines it is part of the site, published by the
 * author of the blog while that author is on the site.
 */
final readonly class SiteLayout
{
    /**
     * It paints from the settings, the images, the redirects, the contents
     * the menus lead to, the authors and the pages, so it reaches them all.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SettingsRepository $settingsRepository,
        private ImageRepository $imageRepository,
        private SiteRedirectsByOrigin $redirectsByOrigin,
        private SiteLinkedContents $linkedContents,
        private AuthorRepository $authorRepository,
        private PageRepository $pageRepository,
    ) {}

    public function page(string $title): Page
    {
        $settings = new SettingsOnSiteFinder()($this->settingsRepository);
        $homeUrl = $this->urlGenerator->generate('web_home');

        if (null === $settings) {
            return WebLayout::page($title, new SiteChrome(null, $homeUrl, [], [], $this->searchUrl(), null));
        }

        $targets = $this->linkedContents->menuTargets([...$settings->mainMenu, ...$settings->footerMenu]);
        $page = WebLayout::page($title, new SiteChrome(
            $settings->name,
            $homeUrl,
            self::links($settings->mainMenu, $targets),
            self::links($settings->footerMenu, $targets),
            $this->searchUrl(),
            $this->shareImage($settings),
        ));
        $home = $this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
        $publisher = $this->publisher($settings);
        $page = $page->withStructuredData(StructuredData::website($home, $settings->name, $publisher['url'] ?? null));

        return null === $publisher ? $page : $page->withStructuredData($publisher['node']);
    }

    /**
     * The address of the page of the author of the blog, the one search
     * engines take as who publishes it, while they are on the site.
     */
    public function publisherUrl(): ?string
    {
        $settings = new SettingsOnSiteFinder()($this->settingsRepository);

        if (null === $settings) {
            return null;
        }

        $publisher = $this->publisher($settings);

        return null === $publisher ? null : $publisher['url'];
    }

    /**
     * The author of the blog, by the address of their page and as a person
     * of schema.org, while on the site.
     *
     * @return ?array{url: string, node: array<string, mixed>}
     */
    private function publisher(SettingsResponse $settings): ?array
    {
        $author = null === $settings->authorId ? null : new AuthorOnSiteFinder($settings->authorId)($this->authorRepository);

        return null === $author ? null : AuthorPerson::node($author, $this->urlGenerator);
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
     * The page that searches the site, while it is on the site.
     */
    private function searchUrl(): ?string
    {
        $slug = new SearchPageFinder()($this->pageRepository)?->slug;

        return null === $slug ? null : $this->urlGenerator->generate('web_page', ['slug' => $slug]);
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
