<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A block that shows the latest posts of the blog, newest first by the day
 * they were first published: as many as it asks for, of a section, of an
 * author and with any of some tags, when it narrows them down to those.
 *
 * It is kept by what it asks for alone: whoever shows it reads the posts
 * on the site, so they are always the latest.
 */
final readonly class Mosaic implements Block
{
    public const int MIN_LIMIT = 1;

    public const int MAX_LIMIT = 20;

    /**
     * @param list<string> $tagIds each once
     */
    private function __construct(
        private int $limit,
        private ?string $sectionId,
        private ?string $authorId,
        private array $tagIds,
    ) {}

    /**
     * @param list<string> $tagIds
     *
     * @throws InvalidRichText when it asks for fewer or more posts than it may, or a filter isn't an id
     */
    public static function asking(int $limit, ?string $sectionId, ?string $authorId, array $tagIds, string $path): self
    {
        if ($limit < self::MIN_LIMIT || $limit > self::MAX_LIMIT) {
            throw InvalidRichText::malformed($path.'.limit', \sprintf('between %d and %d posts', self::MIN_LIMIT, self::MAX_LIMIT));
        }

        $id = static fn (LinkType $type, string $value, string $key): string => new Link($type, $value, $path.'.'.$key)->id();

        return new self(
            $limit,
            null === $sectionId ? null : $id(LinkType::Term, $sectionId, 'section_id'),
            null === $authorId ? null : $id(LinkType::Author, $authorId, 'author_id'),
            array_values(array_unique(array_map(static fn (string $tag): string => $id(LinkType::Term, $tag, 'tag_ids'), $tagIds))),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $optional = static fn (string $key): ?string => null === ($data[$key] ?? null) ? null : Parse::string($data[$key], $path.'.'.$key);
        $tags = [];

        foreach (Parse::list($data['tag_ids'] ?? [], $path.'.tag_ids') as $index => $tag) {
            $tags[] = Parse::string($tag, \sprintf('%s.tag_ids[%d]', $path, $index));
        }

        return self::asking(Parse::int($data['limit'] ?? null, $path.'.limit'), $optional('section_id'), $optional('author_id'), $tags, $path);
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function sectionId(): ?string
    {
        return $this->sectionId;
    }

    public function authorId(): ?string
    {
        return $this->authorId;
    }

    /**
     * @return list<string>
     */
    public function tagIds(): array
    {
        return $this->tagIds;
    }

    /**
     * Ids of what of the kind it narrows the posts down to: sections and tags
     * are terms.
     *
     * @return list<string>
     */
    public function filterIds(LinkType $type): array
    {
        return match ($type) {
            LinkType::Term => [...(null === $this->sectionId ? [] : [$this->sectionId]), ...$this->tagIds],
            LinkType::Author => null === $this->authorId ? [] : [$this->authorId],
            default => [],
        };
    }

    /**
     * Names what it asks for, so mosaics asking for the same posts share them.
     */
    public function key(): string
    {
        $tags = $this->tagIds;
        sort($tags);

        return implode('|', [$this->limit, $this->sectionId ?? '', $this->authorId ?? '', implode(',', $tags)]);
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Mosaic;
    }

    #[\Override]
    public function texts(): array
    {
        return [];
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'type' => $this->type()->value,
            'limit' => $this->limit,
            'section_id' => $this->sectionId,
            'author_id' => $this->authorId,
            'tag_ids' => $this->tagIds,
        ];
    }
}
