<?php

declare(strict_types=1);

namespace Shared\Tests\Ui\Page;

use PHPUnit\Framework\TestCase;
use Shared\Ui\Page\Page;
use Shared\Ui\Page\PageComponent;

final class PageTest extends TestCase
{
    public function testItComposesComponentsIntoTheirRegions(): void
    {
        $page = Page::create('A title')
            ->withHeader('Shared:PageHeader', ['brand' => 'blog autista'])
            ->withMain('Shared:PageTitle', ['title' => 'A heading'])
            ->withFooter('Shared:PageFooter', ['brand' => 'blog autista'])
            ->withMainClass('login');

        self::assertSame('A title', $page->title);
        self::assertSame('login', $page->mainClass);
        self::assertEquals(
            [new PageComponent('Shared:PageHeader', ['brand' => 'blog autista'])],
            $page->header,
        );
        self::assertEquals(
            [new PageComponent('Shared:PageTitle', ['title' => 'A heading'])],
            $page->main,
        );
        self::assertEquals(
            [new PageComponent('Shared:PageFooter', ['brand' => 'blog autista'])],
            $page->footer,
        );
    }

    public function testItIsImmutable(): void
    {
        $original = Page::create('Original');
        $changed = $original->withMain('Shared:PageTitle', ['title' => 'Changed']);

        self::assertSame([], $original->main);
        self::assertNotSame('', $original->mainClass);
        self::assertCount(1, $changed->main);
        self::assertSame('Original', $changed->title);
    }
}
