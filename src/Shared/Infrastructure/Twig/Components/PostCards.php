<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Posts of the blog as a grid of cards, each a link to the post: its
 * opening image, its section, its title, its summary and the day it was
 * first published. A list of a section or a tag takes the width of the page;
 * a mosaic, that of the text it sits in.
 *
 * @phpstan-type PostCard array{url: string, title: string, excerpt: ?string, section: string, date: string, datetime: string, image: ?array{src: string, srcset: string, width: int, height: int}}
 */
#[AsTwigComponent]
final class PostCards
{
    /**
     * @var list<PostCard>
     */
    public array $cards = [];

    /**
     * The level of the heading each title is, one below the heading the
     * cards sit under.
     */
    public int $headingLevel = 2;

    /**
     * Whether they take the width of the page, rather than that of a text.
     */
    public bool $wide = false;

    /**
     * @param list<PostCard> $cards
     */
    public function mount(array $cards, int $headingLevel, bool $wide): void
    {
        $this->cards = $cards;
        $this->headingLevel = $headingLevel;
        $this->wide = $wide;
    }
}
