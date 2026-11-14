<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageId;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PreviewPage;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Infrastructure\Resolution\CmsRedirectsByOrigin;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\PreviewLayout;
use Cms\Shared\Domain\Robots;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Settings\Domain\SettingsMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\RichTextComponents;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PreviewPageTest extends TestCase
{
    private const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fd01';
    private const string PUBLISHED_TARGET = '01a19e72-b07f-7d78-889c-fa4a14a4fd02';
    private const string DRAFT_TARGET = '01a19e72-b07f-7d78-889c-fa4a14a4fd03';
    private const string OTHER_TARGET = '01a19e72-b07f-7d78-889c-fa4a14a4fd04';
    private const string FOOTER_TARGET = '01a19e72-b07f-7d78-889c-fa4a14a4fd05';
    private const string OTHER_FOOTER_TARGET = '01a19e72-b07f-7d78-889c-fa4a14a4fd06';
    private const string PUBLISHED_IMAGE = '01a1b801-ce58-723d-8234-b76d9105f001';
    private const string DRAFT_IMAGE = '01a1b801-ce58-723d-8234-b76d9105f002';

    /**
     * Only what is on the site is painted, as the site would paint it: an
     * image off it is left out, and text linking a page off it stays plain.
     */
    public function testItPaintsOnlyTheImagesAndLinksOnTheSite(): void
    {
        $content = [
            'version' => 1,
            'blocks' => [
                ['type' => 'paragraph', 'text' => [
                    ['text' => 'Publicada', 'marks' => ['link'], 'page_id' => self::PUBLISHED_TARGET],
                    ['text' => 'Borrador', 'marks' => ['link'], 'page_id' => self::DRAFT_TARGET],
                ]],
                ['type' => 'image', 'id' => self::PUBLISHED_IMAGE],
                ['type' => 'image', 'id' => self::DRAFT_IMAGE],
                ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::PUBLISHED_TARGET],
            ],
        ];
        $page = PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi', content: $content);

        $result = $this->preview(
            pages: [
                PageMother::published(id: self::PUBLISHED_TARGET, title: 'Destino editado', slug: 'publicada', publishedTitle: 'Destino publicado'),
                PageMother::create(id: self::DRAFT_TARGET, slug: 'borrador'),
            ],
            images: [
                self::PUBLISHED_IMAGE => ImageMother::published(id: self::PUBLISHED_IMAGE, publishedAlt: 'Alt publicado'),
                self::DRAFT_IMAGE => ImageMother::create(id: self::DRAFT_IMAGE),
            ],
        )(PageResponse::fromPage($page));

        $blocks = $result->main[1]->children;
        self::assertCount(3, $blocks);
        self::assertSame([
            ['href' => '/publicada.html', 'runs' => [['text' => 'Publicada', 'bold' => false]]],
            ['href' => null, 'runs' => [['text' => 'Borrador', 'bold' => false]]],
        ], $blocks[0]->props['text']);
        self::assertSame('Alt publicado', $blocks[1]->props['alt']);
        self::assertSame(['title' => 'Destino publicado', 'url' => '/publicada.html'], $blocks[2]->props);
        self::assertSame('Sobre mí', $result->main[0]->props['title']);
    }

    /**
     * The chrome is the one the site shows now, from its published settings,
     * with a menu leaving out its pages off the site.
     */
    public function testItPaintsTheChromeOfThePublishedSettings(): void
    {
        $settings = SettingsMother::published(
            version: SettingsMother::version(name: 'Nombre editado'),
            published: SettingsMother::version(
                name: 'Nombre publicado',
                mainMenu: [
                    SettingsMother::item(self::PUBLISHED_TARGET, 'Publicada'),
                    SettingsMother::item(self::DRAFT_TARGET, 'Borrador'),
                    SettingsMother::item(self::OTHER_TARGET),
                ],
                footerMenu: [
                    SettingsMother::item(self::FOOTER_TARGET, 'Aviso legal'),
                    SettingsMother::item(self::OTHER_FOOTER_TARGET),
                ],
            ),
        );

        $result = $this->preview(
            pages: [
                PageMother::published(id: self::PUBLISHED_TARGET, slug: 'publicada'),
                PageMother::create(id: self::DRAFT_TARGET, slug: 'borrador'),
                PageMother::published(id: self::OTHER_TARGET, title: 'Contacto', slug: 'contacto'),
                PageMother::published(id: self::FOOTER_TARGET, slug: 'aviso-legal'),
                PageMother::published(id: self::OTHER_FOOTER_TARGET, title: 'Cookies', slug: 'cookies'),
            ],
            settings: $settings,
        )(PageResponse::fromPage(PageMother::create(id: self::PAGE)));

        self::assertSame('Nombre publicado', $result->header[0]->props['brand']);
        self::assertSame([
            ['label' => 'Publicada', 'url' => '/publicada.html'],
            ['label' => 'Contacto', 'url' => '/contacto.html'],
        ], $result->header[0]->props['links']);
        self::assertSame([
            ['label' => 'Aviso legal', 'url' => '/aviso-legal.html'],
            ['label' => 'Cookies', 'url' => '/cookies.html'],
        ], $result->footer[0]->props['links']);
    }

    /**
     * The default image of the published settings is shared with the page,
     * while it is on the site.
     */
    public function testItSharesTheDefaultImageOfThePublishedSettings(): void
    {
        $settings = SettingsMother::published(published: SettingsMother::version(defaultImage: self::PUBLISHED_IMAGE));
        $images = [self::PUBLISHED_IMAGE => ImageMother::published(id: self::PUBLISHED_IMAGE), self::DRAFT_IMAGE => ImageMother::create(id: self::DRAFT_IMAGE)];

        $shared = $this->preview(images: $images, settings: $settings)(PageResponse::fromPage(PageMother::create(id: self::PAGE)));
        $offSite = $this->preview(images: $images, settings: SettingsMother::published(published: SettingsMother::version(defaultImage: self::DRAFT_IMAGE)))(PageResponse::fromPage(PageMother::create(id: self::PAGE)));

        self::assertSame(SiteMedia::shareImage(ImageMother::path(self::PUBLISHED_IMAGE)->value(), ImageMother::ALT), $shared->image);
        self::assertNull($offSite->image);
    }

    public function testItPaintsNoChromeWhileTheSettingsAreNotPublished(): void
    {
        $result = $this->preview(settings: SettingsMother::create())(PageResponse::fromPage(PageMother::create(id: self::PAGE)));

        self::assertNull($result->header[0]->props['brand']);
        self::assertSame([], $result->header[0]->props['links']);
        self::assertNull($result->image);
    }

    /**
     * Search engines never index a preview nor follow its links, whatever
     * the page allows them.
     */
    public function testItKeepsSearchEnginesAwayWhateverThePageAllows(): void
    {
        $page = PageMother::create(id: self::PAGE, slug: 'sobre-mi', metaDescription: 'Resumen', robots: Robots::IndexFollow);

        $result = $this->preview()(PageResponse::fromPage($page));

        self::assertSame('noindex,nofollow', $result->robots);
        self::assertSame('Resumen', $result->description);
        self::assertSame('https://www.blogautista.localhost/sobre-mi.html', $result->canonical);
    }

    /**
     * Seeing a preview is no visit to the site.
     */
    public function testItCountsNoVisitOfThePreview(): void
    {
        $page = PageMother::create(id: self::PAGE, slug: 'sobre-mi');

        $result = $this->preview()(PageResponse::fromPage($page));

        self::assertNull($result->countedPageId);
    }

    /**
     * A page left without a slug has no address of its own on the site yet.
     */
    public function testItGivesAPageWithoutASlugTheRootOfTheSiteAsItsCanonicalUrl(): void
    {
        $result = $this->preview()(PageResponse::fromPage(PageMother::create(id: self::PAGE, slug: '')));

        self::assertSame('https://www.blogautista.localhost/', $result->canonical);
    }

    public function testItGivesTheHomePageTheRootOfTheSiteAsItsCanonicalUrl(): void
    {
        $result = $this->preview()(PageResponse::fromPage(PageMother::home(id: self::PAGE)));

        self::assertSame('https://www.blogautista.localhost/', $result->canonical);
        self::assertNull($result->description);
    }

    /**
     * @param list<Page>           $pages
     * @param array<string, Image> $images keyed by id
     */
    private function preview(array $pages = [], array $images = [], ?Settings $settings = null): PreviewPage
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string {
                $slug = $parameters['slug'] ?? null;
                $path = 'web_home' === $name ? '/' : (\is_string($slug) ? '/'.$slug.'.html' : '');

                return UrlGeneratorInterface::ABSOLUTE_URL === $referenceType ? 'https://www.blogautista.localhost'.$path : $path;
            },
        );
        $settingsRepository = self::createStub(SettingsRepository::class);
        $settingsRepository->method('find')->willReturn($settings);
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturnCallback(
            static function (array $ids) use ($pages): array {
                $found = [];

                foreach ($pages as $page) {
                    foreach ($ids as $id) {
                        if ($id instanceof PageId && $id->value() === $page->id()->value()) {
                            $found[] = $page;
                        }
                    }
                }

                return $found;
            },
        );
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('find')->willReturnCallback(
            static fn (ImageId $id) => $images[$id->value()] ?? null,
        );

        $redirectsByOrigin = new CmsRedirectsByOrigin(self::createStub(RedirectRepository::class));

        return new PreviewPage(
            $urlGenerator,
            new SitePage(new RichTextComponents()),
            new PreviewLayout($urlGenerator, $settingsRepository, $pageRepository, $imageRepository, $redirectsByOrigin),
            $pageRepository,
            $imageRepository,
            $redirectsByOrigin,
        );
    }
}
