<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;

/**
 * A page is described in steps: each region keeps the components it was
 * given, in order, and the class of its main region and its layout are
 * those last given.
 */
final class PageTest extends TestCase
{
    public function testItKeepsEveryComponentOfEachRegionInOrder(): void
    {
        $page = Page::create('Sobre mí')
            ->withHeader('Shared:PageHeader', ['brand' => 'Blog'])
            ->withHeader('Shared:Nav')
            ->withMain('Shared:PageTitle', ['title' => 'Sobre mí'])
            ->withMain('Shared:Prose')
            ->withFooter('Shared:PageFooter')
            ->withFooter('Shared:Nav');

        self::assertEquals([new PageComponent('Shared:PageHeader', ['brand' => 'Blog']), new PageComponent('Shared:Nav')], $page->header);
        self::assertEquals([new PageComponent('Shared:PageTitle', ['title' => 'Sobre mí']), new PageComponent('Shared:Prose')], $page->main);
        self::assertEquals([new PageComponent('Shared:PageFooter'), new PageComponent('Shared:Nav')], $page->footer);
    }

    public function testItTakesTheClassAndTheLayoutLastGiven(): void
    {
        $page = Page::create('Sobre mí')->withMainClass('grid')->withLayout('shared/web_layout.html.twig');

        self::assertSame(['grid', 'shared/web_layout.html.twig'], [$page->mainClass, $page->layout]);
        self::assertNotSame(Page::create('Sobre mí')->mainClass, $page->mainClass);
        self::assertSame(Page::DEFAULT_LAYOUT, Page::create('Sobre mí')->layout);
    }

    /**
     * Each step changes its part alone: whatever the order, the page keeps
     * every part given before, and the page it started from stays as it was.
     */
    public function testEachStepKeepsEveryPartGivenBefore(): void
    {
        $start = Page::create('Sobre mí');

        $page = $start
            ->withHeader('Shared:PageHeader')
            ->withSearchEngineMetadata('Quién escribe', 'index,follow', 'https://blog.test/sobre-mi.html')
            ->withShareImage('/media/a.webp', 1200, 675, 'Un faro')
            ->withCountedVisits('page', 'p1')
            ->withFrames(['https://www.youtube-nocookie.com'])
            ->withStructuredData(['@id' => 'https://blog.test/#web', '@type' => 'WebSite'])
            ->withSiteName('Desde dentro')
            ->withFeed('/feed.xml', 'Desde dentro')
            ->withArticle(new \DateTimeImmutable('2026-11-01T10:00:00+00:00'), new \DateTimeImmutable('2026-11-02T10:00:00+00:00'), 'Vida diaria', ['Ruido'])
            ->withMain('Shared:PageTitle')
            ->withMainMapped(static fn (PageComponent $component): PageComponent => $component)
            ->withFooter('Shared:PageFooter')
            ->withMainClass('grid')
            ->withLayout('shared/web_layout.html.twig');

        self::assertEquals(
            [
                'Sobre mí', [new PageComponent('Shared:PageHeader')], [new PageComponent('Shared:PageTitle')], [new PageComponent('Shared:PageFooter')], 'grid', 'shared/web_layout.html.twig',
                'Quién escribe', 'index,follow', 'https://blog.test/sobre-mi.html',
                ['src' => '/media/a.webp', 'width' => 1200, 'height' => 675, 'alt' => 'Un faro'],
                ['type' => 'page', 'id' => 'p1'],
                ['https://www.youtube-nocookie.com'],
                [['@id' => 'https://blog.test/#web', '@type' => 'WebSite']],
                'Desde dentro',
                ['publishedTime' => '2026-11-01T10:00:00+00:00', 'modifiedTime' => '2026-11-02T10:00:00+00:00', 'section' => 'Vida diaria', 'tags' => ['Ruido']],
                ['url' => '/feed.xml', 'title' => 'Desde dentro'],
            ],
            [
                $page->title, $page->header, $page->main, $page->footer, $page->mainClass, $page->layout,
                $page->description, $page->robots, $page->canonical, $page->image, $page->countedContent, $page->frames,
                $page->structuredData, $page->siteName, $page->article, $page->feed,
            ],
        );
        self::assertEquals(Page::create('Sobre mí'), $start);
    }
}
