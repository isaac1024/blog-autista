<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A stretch of text that shares the same marks.
 */
final readonly class TextRun
{
    /**
     * @param list<Mark> $marks
     */
    public function __construct(
        private string $text,
        private array $marks,
    ) {
        if ('' === $text) {
            throw InvalidRichText::empty('text');
        }
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $data = Parse::object($data, $path);
        $marks = [];

        foreach (Parse::list($data['marks'] ?? [], $path.'.marks') as $index => $mark) {
            $marks[] = Mark::tryFrom(Parse::string($mark, \sprintf('%s.marks[%d]', $path, $index)))
                ?? throw InvalidRichText::malformed(\sprintf('%s.marks[%d]', $path, $index), 'a known mark');
        }

        return new self(Parse::string($data['text'] ?? null, $path.'.text'), array_values(array_unique($marks, \SORT_REGULAR)));
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * @return list<Mark>
     */
    public function marks(): array
    {
        return $this->marks;
    }

    /**
     * @return array{text: string, marks?: list<string>}
     */
    public function toArray(): array
    {
        return [] === $this->marks
            ? ['text' => $this->text]
            : ['text' => $this->text, 'marks' => array_map(static fn (Mark $mark): string => $mark->value, $this->marks)];
    }
}
