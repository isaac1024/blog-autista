<?php

declare(strict_types=1);

namespace Web\Post\Domain;

/**
 * Which posts of the site a list shows: those of a section, of an author and
 * with any of some tags, when it narrows them down to them; all of them
 * when it doesn't. Every list shows them newest first, by the day they were
 * first published.
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

    public static function ofSection(string $id): self
    {
        return new self($id, null, []);
    }

    public static function ofTag(string $id): self
    {
        return new self(null, null, [$id]);
    }

    /**
     * Whether one tag alone narrows it down among the tags, with or without a
     * section or an author: the rows of that tag list it, through their own
     * index.
     */
    public function hasOneTag(): bool
    {
        return 1 === \count($this->tagIds);
    }
}
