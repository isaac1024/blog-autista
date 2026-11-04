<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Section heading. Level 1 is the title of the page, so content starts at 2.
 */
final readonly class Heading implements Block
{
    public const int MIN_LEVEL = 2;
    public const int MAX_LEVEL = 6;

    public function __construct(
        private int $level,
        private InlineText $text,
    ) {
        if ($level < self::MIN_LEVEL || $level > self::MAX_LEVEL) {
            throw InvalidRichText::malformed('heading.level', \sprintf('between %d and %d', self::MIN_LEVEL, self::MAX_LEVEL));
        }

        if ($text->isEmpty()) {
            throw InvalidRichText::empty('heading.text');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        return new self(Parse::int($data['level'] ?? null, $path.'.level'), InlineText::nonEmptyFromArray($data['text'] ?? null, $path.'.text'));
    }

    public function level(): int
    {
        return $this->level;
    }

    public function text(): InlineText
    {
        return $this->text;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Heading;
    }

    #[\Override]
    public function texts(): array
    {
        return [$this->text];
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'level' => $this->level, 'text' => $this->text->toArray()];
    }
}
