<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Shared\Infrastructure\Ui\SiteMedia;
use Shared\Infrastructure\Ui\SitePage;
use Shared\Tests\Domain\RichText\RichTextMother as Text;

/**
 * How the site paints an image it embeds, from every copy it keeps, and
 * which pages a page links to, through its content and the captions of its
 * images.
 */
final class SiteMediaAndPageTest extends TestCase
{
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105d001';
    private const string OTHER = '01a1b801-ce58-723d-8234-b76d9105d002';

    /**
     * An embedded image is painted from its largest copy, and offers every
     * copy for the browser to choose from by its width.
     */
    public function testItOffersEveryCopyOfAnImage(): void
    {
        $image = SiteMedia::image('2026/11/faro.webp', 'Un faro', RichText::empty());

        self::assertSame('/media/2026/11/faro-1200w.webp', $image['src']);
        self::assertSame('/media/2026/11/faro-400w.webp 400w, /media/2026/11/faro-800w.webp 800w, /media/2026/11/faro-1200w.webp 1200w', $image['srcset']);
        self::assertSame([1200, 675, 'Un faro'], [$image['width'], $image['height'], $image['alt']]);
    }

    /**
     * Each page is linked once, in the order it is first linked, from the
     * content first and the captions after.
     */
    public function testItTellsEachPageLinkedOnce(): void
    {
        $link = static fn (string $id): array => ['type' => 'paragraph', 'text' => [['text' => 'enlace', 'marks' => ['link'], 'page_id' => $id]]];
        $content = RichText::fromArray(Text::stored($link(self::PAGE), $link(self::PAGE)));
        $caption = RichText::fromArray(Text::stored($link(self::OTHER), $link(self::PAGE)));

        self::assertSame([self::PAGE, self::OTHER], SitePage::linkedPageIds($content, ['faro' => ['caption' => $caption]]));
    }
}
