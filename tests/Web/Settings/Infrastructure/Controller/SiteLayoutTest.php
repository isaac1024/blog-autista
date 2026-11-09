<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageId;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page as SitePage;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Resolution\SiteRedirectsByOrigin;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteLayout;
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
        self::assertSame([
            'header' => ['brand' => null, 'homeUrl' => '/', 'links' => []],
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
        $pages->method('findByIds')->willReturn([
            SitePage::publish(CmsPagePublishedMother::create(id: Event::PAGE, title: 'Sobre mí', slug: 'sobre-mi')),
            SitePage::publish(CmsPagePublishedMother::create(id: Event::OTHER_PAGE, title: 'Contacto', slug: 'contacto', type: PageType::Contact)),
            SitePage::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Inicio', slug: null, type: PageType::Home)),
        ]);
        $images = self::createStub(ImageRepository::class);
        $images->method('findByIds')->willReturn([]);

        $page = self::layout($settings, $pages, $images)->page('Sobre mí');

        self::assertSame([
            'header' => ['brand' => Event::NAME, 'homeUrl' => '/', 'links' => [
                ['label' => 'Contacto', 'url' => '/contacto.html'],
                ['label' => 'Quién soy', 'url' => '/sobre-mi.html'],
            ]],
            'footer' => ['brand' => Event::NAME, 'links' => [
                ['label' => 'Inicio', 'url' => '/'],
                ['label' => 'Sobre mí', 'url' => '/sobre-mi.html'],
            ]],
            'image' => null,
        ], self::chrome($page));
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

    private static function layout(?Settings $settings, PageRepository $pages, ImageRepository $images): SiteLayout
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

        return new SiteLayout($urlGenerator, $repository, $pages, $images, new SiteRedirectsByOrigin(self::createStub(RedirectRepository::class)));
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
