<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Twig\Components;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Twig\Components\ContentRelated;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * What the card of a content of the blog tells on top: where in the blog it
 * is, as a post has it, by its section and the day of Madrid it was first
 * published.
 */
final class ContentRelatedTest extends TestCase
{
    private const array PICTURE = ['src' => '/media/a-400w.webp', 'srcset' => '/media/a-400w.webp 400w', 'width' => 400, 'height' => 225];

    public function testItTellsAPostBySectionAndTheDayOfMadridItWasFirstPublished(): void
    {
        $card = new ContentRelated(self::translator());
        $card->mount('post', '/vida-diaria/2026/11/08/un-post.html', 'Un post', 'De qué trata.', self::PICTURE, 'Vida diaria', new \DateTimeImmutable('2026-11-07T23:30:00+00:00'), true);

        self::assertSame(['Vida diaria', '8 de noviembre de 2026'], $card->source);
        self::assertSame(
            ['post', '/vida-diaria/2026/11/08/un-post.html', 'Un post', 'De qué trata.', self::PICTURE, true],
            [$card->kind, $card->url, $card->title, $card->summary, $card->image, $card->lead],
        );
    }

    public function testItTellsNothingOnTopOfWhatHasNoSectionNorDay(): void
    {
        $card = new ContentRelated(self::translator());
        $card->mount('section', '/vida-diaria/', 'Vida diaria', null, null, null, null, false);

        self::assertSame([], $card->source);
    }

    public function testItLeavesOutASectionWithoutAName(): void
    {
        $card = new ContentRelated(self::translator());
        $card->mount('post', '/a/2026/11/07/b.html', 'Un post', null, null, '', new \DateTimeImmutable('2026-11-07T10:00:00+00:00'), false);

        self::assertSame(['7 de noviembre de 2026'], $card->source);
    }

    private static function translator(): CatalogueTranslator
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        return $translator;
    }
}
