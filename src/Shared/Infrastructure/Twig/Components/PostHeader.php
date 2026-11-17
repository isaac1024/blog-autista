<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The head of a post: the section it is under, its title, and who signs it,
 * with their avatar, when it was published, or last updated, and how long it
 * takes to read. The section and the author lead to their pages when they
 * have one, and are named as text otherwise.
 */
#[AsTwigComponent]
final class PostHeader
{
    public string $title = '';

    /**
     * Whether it was published again on a later day than the first.
     */
    public bool $updated = false;

    /**
     * The day it was last published, in the reader's language.
     */
    public string $date = '';

    /**
     * The moment it was last published, machine-readable.
     */
    public string $datetime = '';

    public int $readingMinutes = 1;

    /**
     * @var array{name: string, url: ?string}
     */
    public array $section = ['name' => '', 'url' => null];

    /**
     * @var array{name: string, url: ?string, avatar: ?array{src: string, srcset: string, size: int, alt: string}}
     */
    public array $author = ['name' => '', 'url' => null, 'avatar' => null];

    /**
     * @param array{name: string, url: ?string}                                                                      $section
     * @param array{name: string, url: ?string, avatar: ?array{src: string, srcset: string, size: int, alt: string}} $author
     */
    public function mount(string $title, bool $updated, string $date, string $datetime, int $readingMinutes, array $section, array $author): void
    {
        $this->title = $title;
        $this->updated = $updated;
        $this->date = $date;
        $this->datetime = $datetime;
        $this->readingMinutes = $readingMinutes;
        $this->section = $section;
        $this->author = $author;
    }
}
