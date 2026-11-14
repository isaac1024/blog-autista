<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * When a post was published, or last updated, and who signs it under which
 * section; each leads to its page when it has one, and is named as text
 * otherwise.
 */
#[AsTwigComponent]
final class PostMeta
{
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

    /**
     * @var array{name: string, url: ?string}
     */
    public array $section = ['name' => '', 'url' => null];

    /**
     * @var array{name: string, url: ?string}
     */
    public array $author = ['name' => '', 'url' => null];

    /**
     * @param array{name: string, url: ?string} $section
     * @param array{name: string, url: ?string} $author
     */
    public function mount(bool $updated, string $date, string $datetime, array $section, array $author): void
    {
        $this->updated = $updated;
        $this->date = $date;
        $this->datetime = $datetime;
        $this->section = $section;
        $this->author = $author;
    }
}
