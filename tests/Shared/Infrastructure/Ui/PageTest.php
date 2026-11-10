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
}
