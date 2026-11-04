<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Paragraphs set apart from the text around them, such as a note.
 */
final readonly class Callout implements Block
{
    /**
     * @param list<InlineText> $paragraphs
     */
    public function __construct(
        private CalloutVariant $variant,
        private array $paragraphs,
    ) {
        Parse::assertParagraphs($paragraphs, 'callout.paragraphs');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        return new self(
            CalloutVariant::tryFrom(Parse::string($data['variant'] ?? null, $path.'.variant'))
                ?? throw InvalidRichText::malformed($path.'.variant', 'a known callout variant'),
            Parse::paragraphs($data['paragraphs'] ?? null, $path.'.paragraphs'),
        );
    }

    public function variant(): CalloutVariant
    {
        return $this->variant;
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
        return BlockType::Callout;
    }

    #[\Override]
    public function texts(): array
    {
        return $this->paragraphs;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'type' => $this->type()->value,
            'variant' => $this->variant->value,
            'paragraphs' => array_map(static fn (InlineText $text): array => $text->toArray(), $this->paragraphs),
        ];
    }
}
