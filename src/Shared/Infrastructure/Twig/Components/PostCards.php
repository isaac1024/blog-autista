<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Posts of the blog as a grid of cards, each a link to the post: its
 * opening image, its section, its title, its summary and the day it was
 * first published. They sit in the column of reading, whether a list of a
 * section or a tag or a mosaic, in one column, or in two from tablets.
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
     * Whether its first card has the first picture of what the page shows, which the
     * browser fetches at once rather than once it nears the screen, as it
     * may be the largest thing on it as it opens.
     */
    public bool $lead = false;

    /**
     * @param list<PostCard> $cards
     */
    public function mount(array $cards, int $headingLevel, bool $lead): void
    {
        $this->lead = $lead;
        $this->cards = $cards;
        $this->headingLevel = $headingLevel;
    }
}
