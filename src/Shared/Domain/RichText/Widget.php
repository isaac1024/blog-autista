<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A block that shows something the content points to, rather than text
 * written in it: a page, a post, a section, a tag or an author of the blog
 * as a card of related content.
 *
 * It is kept by its kind and id alone, as a link is: whoever shows it reads
 * its name, and whether it is on the site, where it is kept, so they are
 * always the latest.
 */
final readonly class Widget implements Block
{
    public function __construct(
        private WidgetKind $kind,
        private Link $target,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $kind = WidgetKind::tryFrom(Parse::string($data['kind'] ?? null, $path.'.kind'))
            ?? throw InvalidRichText::malformed($path.'.kind', 'a known kind of widget');

        return new self($kind, Link::fromArray($data, $path) ?? throw InvalidRichText::malformed($path.'.page_id', 'the id of what it shows'));
    }

    public function kind(): WidgetKind
    {
        return $this->kind;
    }

    public function target(): Link
    {
        return $this->target;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Widget;
    }

    #[\Override]
    public function texts(): array
    {
        return [];
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'kind' => $this->kind->value, ...$this->target->toArray()];
    }
}
