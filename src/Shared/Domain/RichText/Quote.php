<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Quotation of one or more paragraphs.
 */
final readonly class Quote implements Block
{
    /**
     * @param list<InlineText> $paragraphs
     */
    public function __construct(private array $paragraphs)
    {
        Parse::assertParagraphs($paragraphs, 'quote.paragraphs');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        return new self(Parse::paragraphs($data['paragraphs'] ?? null, $path.'.paragraphs'));
    }

    /**
     * @return list<InlineText>
     */
    public function paragraphs(): array
    {
        return $this->paragraphs;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Quote;
    }

    #[\Override]
    public function texts(): array
    {
        return $this->paragraphs;
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'paragraphs' => array_map(static fn (InlineText $text): array => $text->toArray(), $this->paragraphs)];
    }
}
