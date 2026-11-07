<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Quotation of one or more paragraphs, with where it comes from when that is
 * known: who said it, or the work or article it is taken from.
 */
final readonly class Quote implements Block
{
    /**
     * @param list<InlineText> $paragraphs
     * @param null|string      $source     plain text, without marks; null when unknown
     */
    public function __construct(
        private array $paragraphs,
        private ?string $source,
    ) {
        Parse::assertParagraphs($paragraphs, 'quote.paragraphs');

        if (null !== $source && '' === trim($source)) {
            throw InvalidRichText::empty('quote.source');
        }
    }

    /**
     * Documents stored before quotes had a source have none.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $source = $data['source'] ?? null;

        return new self(
            Parse::paragraphs($data['paragraphs'] ?? null, $path.'.paragraphs'),
            null === $source ? null : Parse::string($source, $path.'.source'),
        );
    }

    /**
     * @return list<InlineText>
     */
    public function paragraphs(): array
    {
        return $this->paragraphs;
    }

    public function source(): ?string
    {
        return $this->source;
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
        $data = ['type' => $this->type()->value, 'paragraphs' => array_map(static fn (InlineText $text): array => $text->toArray(), $this->paragraphs)];

        if (null !== $this->source) {
            $data['source'] = $this->source;
        }

        return $data;
    }
}
