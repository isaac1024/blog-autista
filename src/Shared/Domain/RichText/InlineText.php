<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * Text within a block, made of runs that each carry their own marks.
 */
final readonly class InlineText
{
    /**
     * @param list<TextRun> $runs
     */
    public function __construct(private array $runs) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $runs = [];

        foreach (Parse::list($data, $path) as $index => $run) {
            $runs[] = TextRun::fromArray($run, \sprintf('%s[%d]', $path, $index));
        }

        return new self($runs);
    }

    /**
     * Same as fromArray(), but the text must have at least one run.
     */
    public static function nonEmptyFromArray(mixed $data, string $path): self
    {
        $text = self::fromArray($data, $path);

        if ($text->isEmpty()) {
            throw InvalidRichText::empty($path);
        }

        return $text;
    }

    public function isEmpty(): bool
    {
        return [] === $this->runs;
    }

    /**
     * @return list<TextRun>
     */
    public function runs(): array
    {
        return $this->runs;
    }

    /**
     * @return list<Mark>
     */
    public function marks(): array
    {
        return array_values(array_unique(array_merge(...array_map(static fn (TextRun $run): array => $run->marks(), $this->runs)), \SORT_REGULAR));
    }

    /**
     * @return list<array{text: string, marks?: list<string>, page_id?: string, post_id?: string, term_id?: string, author_id?: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (TextRun $run): array => $run->toArray(), $this->runs);
    }
}
