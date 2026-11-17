<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Domain\Uuid;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\StructuredData;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\External\Domain\ExternalResourceRepository as WebExternalResourceRepository;
use Web\External\Infrastructure\Ui\SiteExternalViews;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page as SitePage;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteLinkedContents;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother as Event;

/**
 * What the layout of the site is told to paint around a page, from the
 * settings on the site: never the markup, which the ui-check harness covers.
 */
final class SiteLayoutTest extends TestCase
{
    private const string HOME = '01a19e72-b07f-7d78-889c-fa4a14a4fb90';

    private const string OFF_SITE = '01a19e72-b07f-7d78-889c-fa4a14a4fb99';

    public function testItPaintsNoChromeUntilTheSettingsArePublished(): void
    {
        $pages = $this->createMock(PageRepository::class);
        $pages->expects(self::never())->method('findByIds');
        $images = $this->createMock(ImageRepository::class);
        $images->expects(self::never())->method('findByIds');

        $page = self::layout(null, $pages, $images)->page('Sobre mí');

        self::assertSame('Sobre mí', $page->title);
        self::assertNull($page->siteName);
        self::assertSame([
            'header' => ['brand' => null, 'homeUrl' => '/', 'links' => [], 'searchUrl' => null],
            'footer' => ['brand' => null, 'links' => []],
            'image' => null,
        ], self::chrome($page));
    }

    /**
     * Each menu keeps its order and leaves out its pages off the site; an
     * entry without a label takes the title of its page.
     */
    public function testItPaintsTheNameAndTheMenusWithThePagesOnTheSite(): void
    {
        $settings = Settings::publish(Event::create(
            mainMenu: [
                ['page_id' => self::OFF_SITE, 'label' => 'No publicada'],
                ['page_id' => Event::OTHER_PAGE, 'label' => null],
                ['page_id' => Event::PAGE, 'label' => 'Quién soy'],
            ],
            footerMenu: [['page_id' => self::HOME, 'label' => null], ['page_id' => Event::PAGE, 'label' => null]],
        ));
        $pages = self::createStub(PageRepository::class);
        $onSite = [
            SitePage::publish(CmsPagePublishedMother::create(id: Event::PAGE, title: 'Sobre mí', slug: 'sobre-mi')),
            SitePage::publish(CmsPagePublishedMother::create(id: Event::OTHER_PAGE, title: 'Contacto', slug: 'contacto', type: PageType::Contact)),
            SitePage::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Inicio', slug: null, type: PageType::Home)),
        ];
        // Only the pages asked for are found, as the site would.
        $pages->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(
            $onSite,
            static fn (SitePage $page): bool => \in_array($page->id()->value(), array_map(static fn (mixed $id): string => $id instanceof Uuid ? $id->value() : '', $ids), true),
        )));
        $images = self::createStub(ImageRepository::class);
        $images->method('findByIds')->willReturn([]);

        $page = self::layout($settings, $pages, $images)->page('Sobre mí');

        self::assertSame(Event::NAME, $page->siteName);
        self::assertSame([
            'header' => ['brand' => Event::NAME, 'homeUrl' => '/', 'links' => [
                ['label' => 'Contacto', 'url' => '/contacto.html'],
                ['label' => 'Quién soy', 'url' => '/sobre-mi.html'],
            ], 'searchUrl' => null],
            'footer' => ['brand' => Event::NAME, 'links' => [
                ['label' => 'Inicio', 'url' => '/'],
                ['label' => 'Sobre mí', 'url' => '/sobre-mi.html'],
            ]],
            'image' => null,
        ], self::chrome($page));
    }

    /**
     * The header leads to the page that searches the site while it is on
     * the site, whether the settings are published or not.
     */
    public function testItLeadsToTheSearchOfTheSiteWhileItsPageIsOnIt(): void
    {
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $pages->method('findSearch')->willReturn(SitePage::publish(CmsPagePublishedMother::create(id: '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b99', title: 'Buscar', slug: 'buscar', type: PageType::Search)));
        $images = self::createStub(ImageRepository::class);
        $settings = Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: []));

        self::assertSame('/buscar.html', self::chrome(self::layout($settings, $pages, $images)->page('Sobre mí'))['header']['searchUrl']);
        self::assertSame('/buscar.html', self::chrome(self::layout(null, $pages, $images)->page('Sobre mí'))['header']['searchUrl']);
        self::assertNull(self::chrome(self::layout($settings, self::createStub(PageRepository::class), $images)->page('Sobre mí'))['header']['searchUrl']);
    }

    public function testItSharesTheLargestCopyOfTheDefaultImageWhileItIsOnTheSite(): void
    {
        $settings = Settings::publish(Event::create(mainMenu: [], footerMenu: []));
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $images = $this->createMock(ImageRepository::class);
        $images->expects(self::once())->method('findByIds')->with(self::equalTo([new ImageId(Event::IMAGE)]))->willReturn([
            Image::publish(CmsImagePublishedMother::create(id: Event::IMAGE, alt: 'Rectángulo amarillo', path: '2026/11/'.Event::IMAGE.'.webp')),
        ]);

        $page = self::layout($settings, $pages, $images)->page('Sobre mí');

        self::assertSame(
            ['src' => '/media/2026/11/'.Event::IMAGE.'-1200w.webp', 'width' => 1200, 'height' => 675, 'alt' => 'Rectángulo amarillo'],
            $page->image,
        );
    }

    public function testItSharesNoImageWhenTheDefaultOneIsOffTheSite(): void
    {
        $settings = Settings::publish(Event::create(mainMenu: [], footerMenu: []));
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $images = self::createStub(ImageRepository::class);
        $images->method('findByIds')->willReturn([]);

        self::assertNull(self::layout($settings, $pages, $images)->page('Sobre mí')->image);
    }

    public function testItLooksUpNoImageWhenTheSettingsHaveNone(): void
    {
        $settings = Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: []));
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $images = $this->createMock(ImageRepository::class);
        $images->expects(self::never())->method('findByIds');

        self::assertNull(self::layout($settings, $pages, $images)->page('Sobre mí')->image);
    }

    /**
     * Every page is part of the site, named after the blog, published by the
     * author of the blog while that author is on the site: one person, by
     * the address of their page.
     */
    public function testItTellsSearchEnginesTheSiteAndWhoPublishesIt(): void
    {
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $images = self::createStub(ImageRepository::class);
        $author = Author::publish(CmsAuthorPublishedMother::create());
        $authors = self::createStub(AuthorRepository::class);
        $authors->method('find')->willReturn($author);
        $layout = self::layout(Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: [], authorId: AuthorIdMother::DEFAULT_VALUE)), $pages, $images, $authors);

        self::assertSame([
            ['@type' => 'WebSite', '@id' => '/#website', 'url' => '/', 'name' => Event::NAME, 'inLanguage' => 'es', 'publisher' => ['@id' => '/isaac.html#person']],
            [
                '@type' => 'Person',
                '@id' => '/isaac.html#person',
                'url' => '/isaac.html',
                'name' => 'Isaac Abreu',
                'image' => SiteMedia::avatar($author->avatarName(), 'Isaac Abreu')['src'],
                'description' => 'Quién escribe este blog.',
            ],
        ], $layout->page('Sobre mí')->structuredData);
        self::assertSame('/isaac.html', $layout->publisherUrl());

        // On the page of that author, the person is told once, as the page tells it.
        $profile = StructuredData::person('/isaac.html', 'Isaac Abreu', null, 'Otra descripción.');
        $graph = $layout->page('Isaac Abreu')->withStructuredData(['@type' => 'ProfilePage'])->withStructuredData($profile)->structuredData;
        self::assertSame(['WebSite', 'ProfilePage', 'Person'], array_column($graph, '@type'));
        self::assertSame($profile, $graph[2]);
    }

    /**
     * An author of the blog off the site, or none, publishes nothing; until
     * the settings are published, the site tells nothing.
     */
    public function testItTellsOfNoPublisherOffTheSite(): void
    {
        $pages = self::createStub(PageRepository::class);
        $pages->method('findByIds')->willReturn([]);
        $images = self::createStub(ImageRepository::class);
        $authors = self::createStub(AuthorRepository::class);
        $authors->method('find')->willReturn(null);

        foreach ([AuthorIdMother::DEFAULT_VALUE, null] as $authorId) {
            $layout = self::layout(Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: [], authorId: $authorId)), $pages, $images, $authors);

            self::assertSame([['@type' => 'WebSite', '@id' => '/#website', 'url' => '/', 'name' => Event::NAME, 'inLanguage' => 'es']], $layout->page('Sobre mí')->structuredData);
            self::assertNull($layout->publisherUrl());
        }

        $unpublished = self::layout(null, $pages, $images, $authors);
        self::assertSame([], $unpublished->page('Sobre mí')->structuredData);
        self::assertNull($unpublished->publisherUrl());
    }

    private static function layout(?Settings $settings, PageRepository $pages, ImageRepository $images, ?AuthorRepository $authors = null): SiteLayout
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                $slug = $parameters['slug'] ?? null;

                return 'web_home' === $name ? '/' : (\is_string($slug) ? '/'.$slug.'.html' : '');
            },
        );
        $repository = self::createStub(SettingsRepository::class);
        $repository->method('find')->willReturn($settings);

        return new SiteLayout($urlGenerator, $repository, $images, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteLinkedContents($urlGenerator, $pages, self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)), new SiteExternalViews(self::createStub(WebExternalResourceRepository::class), $pages, $urlGenerator), self::createStub(ImageRepository::class)), $authors ?? self::createStub(AuthorRepository::class), $pages);
    }

    /**
     * What the header and the footer are told, and the image shared.
     *
     * @return array{header: array<string, mixed>, footer: array<string, mixed>, image: mixed}
     */
    private static function chrome(Page $page): array
    {
        return ['header' => $page->header[0]->props, 'footer' => $page->footer[0]->props, 'image' => $page->image];
    }
}
