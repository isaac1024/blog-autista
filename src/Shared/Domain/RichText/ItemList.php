<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Ordered or unordered list of single-paragraph items, without nesting.
 */
final readonly class ItemList implements Block
{
    /**
     * @param list<InlineText> $items
     */
    public function __construct(
        private ListStyle $style,
        private array $items,
    ) {
        Parse::assertParagraphs($items, 'list.items');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        return new self(
            ListStyle::tryFrom(Parse::string($data['style'] ?? null, $path.'.style'))
                ?? throw InvalidRichText::malformed($path.'.style', 'a known list style'),
            Parse::paragraphs($data['items'] ?? null, $path.'.items'),
        );
    }

    public function style(): ListStyle
    {
        return $this->style;
    }

    /**
     * @return list<InlineText>
     */
    public function items(): array
    {
        return $this->items;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::List;
    }

    #[\Override]
    public function texts(): array
    {
        return $this->items;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'type' => $this->type()->value,
            'style' => $this->style->value,
            'items' => array_map(static fn (InlineText $text): array => $text->toArray(), $this->items),
        ];
    }
}
