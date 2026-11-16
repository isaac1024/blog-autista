<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * Which posts on the site a list shows, as the CMS previews it: those of a
 * section, of an author and with any of some tags, when it narrows them down
 * to them; all of them when it doesn't. Newest first, by the day they were
 * first published, as the site lists them.
 */
final readonly class PostSelection
{
    /**
     * @param list<string> $tagIds any of them
     */
    public function __construct(
        public ?string $sectionId,
        public ?string $authorId,
        public array $tagIds,
    ) {}
}
