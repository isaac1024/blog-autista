<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * The posts, the sections and tags, the authors and the external resources
 * some rich texts link to or show in widgets, such as a version being edited and the one on the
 * site, each once and in order.
 */
final readonly class LinkedContents
{
    /**
     * @param list<string> $posts
     * @param list<string> $terms
     * @param list<string> $authors
     * @param list<string> $externals
     */
    private function __construct(
        private array $posts,
        private array $terms,
        private array $authors,
        private array $externals,
    ) {}

    public static function fromTexts(?RichText ...$texts): self
    {
        $ids = static function (LinkType $type) use ($texts): array {
            $ids = [];

            foreach ($texts as $text) {
                array_push($ids, ...($text?->linkedIds($type) ?? []));
            }

            sort($ids);

            return array_values(array_unique($ids));
        };

        return new self($ids(LinkType::Post), $ids(LinkType::Term), $ids(LinkType::Author), $ids(LinkType::External));
    }

    /**
     * @return list<string>
     */
    public function posts(): array
    {
        return $this->posts;
    }

    /**
     * @return list<string>
     */
    public function terms(): array
    {
        return $this->terms;
    }

    /**
     * @return list<string>
     */
    public function authors(): array
    {
        return $this->authors;
    }

    /**
     * @return list<string>
     */
    public function externals(): array
    {
        return $this->externals;
    }

    public function isEmpty(): bool
    {
        return [] === $this->posts && [] === $this->terms && [] === $this->authors && [] === $this->externals;
    }

    public function equals(self $other): bool
    {
        return $this->posts === $other->posts && $this->terms === $other->terms && $this->authors === $other->authors && $this->externals === $other->externals;
    }
}
