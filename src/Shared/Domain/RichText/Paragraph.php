<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

final readonly class Paragraph implements Block
{
    public function __construct(private InlineText $text)
    {
        if ($text->isEmpty()) {
            throw InvalidRichText::empty('paragraph.text');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        return new self(InlineText::nonEmptyFromArray($data['text'] ?? null, $path.'.text'));
    }

    public function text(): InlineText
    {
        return $this->text;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Paragraph;
    }

    #[\Override]
    public function texts(): array
    {
        return [$this->text];
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'text' => $this->text->toArray()];
    }
}
